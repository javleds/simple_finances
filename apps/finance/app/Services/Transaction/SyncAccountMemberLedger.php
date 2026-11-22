<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Services\Accounts\BuildAccountCustodySummary;

use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SyncAccountMemberLedger
{
    public function __construct(
        private readonly BuildAccountCustodySummary $buildAccountCustodySummary,
        private readonly \App\Services\Accounts\BuildAccountMemberSummary $buildAccountMemberSummary,
    ) {
    }

    public function execute(Transaction $transaction): void
    {
        $transaction->ledgerEntries()->delete();

        if (TransactionStatus::Completed !== $transaction->status || null !== $transaction->legacy_migrated_at) {
            return;
        }

        if (TransactionType::Income === $transaction->type) {
            $this->recordIncomeCustody($transaction);

            return;
        }

        $this->recordOutcome($transaction);
    }

    private function recordIncomeCustody(Transaction $transaction): void
    {
        $transaction->ledgerEntries()->create([
            'account_id'  => $transaction->account_id,
            'user_id'     => $transaction->custodian_user_id ?? $transaction->user_id,
            'type'        => AccountMemberLedgerEntryType::IncomeCustody,
            'amount'      => $transaction->amount,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
    }

    private function recordOutcome(Transaction $transaction): void
    {
        if (false === $transaction->uses_shared_account_rules) {
            $this->createEntry($transaction, $transaction->paid_by_user_id ?? $transaction->user_id, AccountMemberLedgerEntryType::AccountFundExpense, Decimal::multiply($transaction->amount, -1));

            return;
        }

        $source = $transaction->payment_source ?? TransactionPaymentSource::AccountFund;
        $paidByUserId = $transaction->paid_by_user_id ?? $transaction->user_id;

        if (TransactionPaymentSource::AccountFund === $source) {
            $this->recordAccountFundOutcome($transaction, $paidByUserId);

            return;
        }

        $positiveCustodians = $this->positiveCustodians($transaction);

        if ($transaction->uses_virtual_custodian_rules) {
            $this->recordVirtualAccountOutOfPocketOutcome($transaction, $positiveCustodians, $paidByUserId);

            return;
        }

        if ($positiveCustodians->isNotEmpty()) {
            $this->recordOutOfPocketOutcomeWithCustody($transaction);

            return;
        }

        $transaction->ledgerEntries()->create([
            'account_id'  => $transaction->account_id,
            'user_id'     => $paidByUserId,
            'type'        => AccountMemberLedgerEntryType::ExpensePaid,
            'amount'      => $transaction->amount,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);

        $allocations = $transaction->allocations->isNotEmpty()
            ? $transaction->allocations
            : collect([(object) ['user_id' => $transaction->user_id, 'amount' => $transaction->amount]]);

        foreach ($allocations as $allocation) {
            $transaction->ledgerEntries()->create([
                'account_id'      => $transaction->account_id,
                'user_id'         => $allocation->user_id,
                'related_user_id' => $paidByUserId,
                'type'            => AccountMemberLedgerEntryType::ExpenseShare,
                'amount'          => Decimal::multiply($allocation->amount, -1),
                'description'     => $transaction->concept,
                'occurred_at'     => $transaction->scheduled_at,
            ]);
        }
    }

    private function recordAccountFundOutcome(Transaction $transaction, int $paidByUserId): void
    {
        $hasExplicitAllocations = $transaction->allocations->isNotEmpty();
        $allocations = $this->responsibilityAllocations($transaction, $paidByUserId);
        $custodies = $this->positiveCustodians($transaction)
            ->mapWithKeys(fn (array $custodian): array => [(int) $custodian['user_id'] => Decimal::normalize($custodian['amount'])])
            ->all();
        $positiveCustodyTotal = Decimal::sum($custodies);
        $deficit = Decimal::max('0.00', Decimal::subtract(Decimal::normalize($transaction->amount), $positiveCustodyTotal));
        $coveredAmount = Decimal::subtract(Decimal::normalize($transaction->amount), $deficit);
        $coveredByOthers = [];

        foreach ($allocations as $allocation) {
            if (Decimal::compare($coveredAmount, '0.00') <= 0) {
                break;
            }

            $remaining = Decimal::min(Decimal::normalize($allocation->amount), $coveredAmount);
            $ownUsed = $this->consumeCustody($custodies, (int) $allocation->user_id, $remaining);

            if (Decimal::compare($ownUsed, '0.00') > 0) {
                $this->createEntry($transaction, (int) $allocation->user_id, AccountMemberLedgerEntryType::AccountFundExpense, Decimal::multiply($ownUsed, -1));
                $remaining = Decimal::subtract($remaining, $ownUsed);
                $coveredAmount = Decimal::subtract($coveredAmount, $ownUsed);
            }

            foreach ($custodies as $custodianUserId => $available) {
                if (Decimal::compare($remaining, '0.00') <= 0 || Decimal::compare($coveredAmount, '0.00') <= 0) {
                    break;
                }

                if ((int) $custodianUserId === (int) $allocation->user_id || Decimal::compare($available, '0.00') <= 0) {
                    continue;
                }

                $used = $this->consumeCustody($custodies, (int) $custodianUserId, Decimal::min($remaining, $coveredAmount));

                if (Decimal::compare($used, '0.00') <= 0) {
                    continue;
                }

                $this->createEntry($transaction, (int) $custodianUserId, AccountMemberLedgerEntryType::AccountFundExpense, Decimal::multiply($used, -1));

                if (Decimal::compare($deficit, '0.00') <= 0 && ! $hasExplicitAllocations) {
                    $coveredByOthers[(int) $custodianUserId] = Decimal::add($coveredByOthers[(int) $custodianUserId] ?? '0.00', $used);
                }

                $remaining = Decimal::subtract($remaining, $used);
                $coveredAmount = Decimal::subtract($coveredAmount, $used);
            }
        }

        if (Decimal::compare($deficit, '0.00') > 0) {
            $receiverUserId = $this->primaryCustodianUserId($transaction, $paidByUserId);

            foreach ($this->deficitAmounts($allocations, $deficit) as $userId => $amount) {
                $this->createEntry(
                    transaction: $transaction,
                    userId: (int) $userId,
                    type: AccountMemberLedgerEntryType::AccountDeficitShare,
                    amount: Decimal::multiply($amount, -1),
                    relatedUserId: $receiverUserId,
                );
            }

            return;
        }

        foreach ($coveredByOthers as $custodianUserId => $amount) {
            $this->createCustodyReimbursementDue($transaction, (int) $custodianUserId, $paidByUserId, $amount);
        }
    }

    private function recordOutOfPocketOutcomeWithCustody(Transaction $transaction): void
    {
        $allocations = $this->responsibilityAllocations($transaction, $transaction->user_id);
        $custodies = $this->positiveCustodians($transaction)
            ->mapWithKeys(fn (array $custodian): array => [(int) $custodian['user_id'] => Decimal::normalize($custodian['amount'])])
            ->all();

        foreach ($allocations as $allocation) {
            $remaining = Decimal::normalize($allocation->amount);
            $ownAmount = $this->consumeCustody($custodies, (int) $allocation->user_id, $remaining);

            if (Decimal::compare($ownAmount, '0.00') > 0) {
                $this->createCustodyReimbursementDue($transaction, (int) $allocation->user_id, (int) $allocation->user_id, $ownAmount);
                $remaining = Decimal::subtract($remaining, $ownAmount);
            }

            foreach ($custodies as $custodianUserId => $available) {
                if (Decimal::compare($remaining, '0.00') <= 0) {
                    break;
                }

                if (Decimal::compare($available, '0.00') <= 0) {
                    continue;
                }

                $used = $this->consumeCustody($custodies, (int) $custodianUserId, $remaining);

                if (Decimal::compare($used, '0.00') <= 0) {
                    continue;
                }

                $this->createCustodyReimbursementDue($transaction, (int) $custodianUserId, (int) $allocation->user_id, $used);
                $remaining = Decimal::subtract($remaining, $used);
            }
        }
    }

    private function recordVirtualAccountOutOfPocketOutcome(Transaction $transaction, Collection $custodians, int $payerId): void
    {
        $remaining = Decimal::normalize($transaction->amount);

        foreach ($custodians as $custodian) {
            $covered = Decimal::min($remaining, Decimal::normalize($custodian['amount']));
            $this->createCustodyReimbursementDue($transaction, (int) $custodian['user_id'], $payerId, $covered, AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue);
            $remaining = Decimal::subtract($remaining, $covered);

            if (Decimal::compare($remaining, '0.00') <= 0) {
                return;
            }
        }

        $this->createEntry($transaction, $payerId, AccountMemberLedgerEntryType::ExpensePaid, $remaining);
        $allocations = $this->responsibilityAllocations($transaction, $transaction->user_id);

        foreach ($this->deficitAmounts($allocations, $remaining) as $userId => $amount) {
            $this->createEntry($transaction, (int) $userId, AccountMemberLedgerEntryType::ExpenseShare, Decimal::negate($amount), $payerId);
        }
    }

    private function createCustodyReimbursementDue(Transaction $transaction, int $fromUserId, int $toUserId, string $amount, AccountMemberLedgerEntryType $type = AccountMemberLedgerEntryType::CustodyReimbursementDue): void
    {
        if (Decimal::compare($amount, '0.00') <= 0) {
            return;
        }

        if ($fromUserId === $toUserId) {
            $this->createEntry($transaction, $fromUserId, $type, Decimal::multiply($amount, -1), $toUserId);

            return;
        }

        $this->createEntry($transaction, $fromUserId, $type, Decimal::multiply($amount, -1), $toUserId);
        $this->createEntry($transaction, $toUserId, $type, $amount, $fromUserId);
    }

    private function createEntry(
        Transaction $transaction,
        int $userId,
        AccountMemberLedgerEntryType $type,
        string $amount,
        ?int $relatedUserId = null,
    ): AccountMemberLedgerEntry {
        return $transaction->ledgerEntries()->create([
            'account_id'      => $transaction->account_id,
            'user_id'         => $userId,
            'related_user_id' => $relatedUserId,
            'type'            => $type,
            'amount'          => Decimal::round($amount, 2),
            'description'     => $transaction->concept,
            'occurred_at'     => $transaction->scheduled_at,
        ]);
    }

    private function consumeCustody(array &$custodies, int $userId, string $amount): string
    {
        $available = Decimal::normalize($custodies[$userId] ?? '0.00');
        $used = Decimal::min($available, $amount);

        if (Decimal::compare($used, '0.00') <= 0) {
            return '0.00';
        }

        $custodies[$userId] = Decimal::subtract($available, $used);

        return $used;
    }

    private function primaryCustodianUserId(Transaction $transaction, int $fallbackUserId): int
    {
        return (int) ($this->positiveCustodians($transaction)->first()['user_id'] ?? $fallbackUserId);
    }

    private function positiveCustodians(Transaction $transaction): Collection
    {
        $account = $transaction->account()->withoutGlobalScopes()->firstOrFail();
        $custodians = $this->buildAccountCustodySummary->positiveCustodians($account);

        if ($transaction->uses_virtual_custodian_rules) {
            $pending = collect($this->buildAccountMemberSummary->execute($account)['pending_reimbursements'])
                ->where('action_type', 'custody_to_user');

            return $custodians->map(fn (array $custodian): array => [
                'user_id' => $custodian['user_id'],
                'amount'  => Decimal::max('0.00', Decimal::subtract($custodian['amount'], Decimal::sum($pending->where('from_user_id', $custodian['user_id']), 'amount'))),
            ])->filter(fn (array $custodian): bool => Decimal::compare($custodian['amount'], 0) > 0)->values();
        }

        if ($custodians->isNotEmpty() || Decimal::compare(Decimal::normalize($account->balance), '0.00') <= 0) {
            return $custodians;
        }

        return collect([[
            'user_id' => (int) $account->user_id,
            'amount'  => Decimal::normalize($account->balance),
        ]]);
    }

    private function responsibilityAllocations(Transaction $transaction, int $fallbackUserId): Collection
    {
        return $transaction->allocations->isNotEmpty()
            ? $transaction->allocations
            : collect([(object) ['user_id' => $fallbackUserId, 'amount' => $transaction->amount, 'percentage' => '100.00']]);
    }

    private function deficitAmounts(Collection $allocations, string $deficit): array
    {
        $total = Decimal::sum($allocations, 'amount');

        if (Decimal::compare($total, '0.00') <= 0) {
            return [];
        }

        $allocated = '0.00';
        $lastIndex = $allocations->count() - 1;
        $amounts = [];

        foreach ($allocations->values() as $index => $allocation) {
            $amount = $index === $lastIndex
                ? Decimal::subtract($deficit, $allocated)
                : Decimal::ratio($deficit, Decimal::normalize($allocation->amount), $total);

            if (Decimal::compare($amount, 0) < 0) {
                throw ValidationException::withMessages(['user_payments' => 'The uncovered amount cannot be allocated without a negative remainder.']);
            }
            $allocated = Decimal::add($allocated, $amount);
            $amounts[(int) $allocation->user_id] = Decimal::add($amounts[(int) $allocation->user_id] ?? '0.00', $amount);
        }

        return $amounts;
    }
}
