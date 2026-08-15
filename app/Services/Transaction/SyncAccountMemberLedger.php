<?php

namespace App\Services\Transaction;

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Services\Accounts\BuildAccountCustodySummary;
use Illuminate\Support\Collection;

class SyncAccountMemberLedger
{
    public function __construct(private readonly BuildAccountCustodySummary $buildAccountCustodySummary) {}

    public function execute(Transaction $transaction): void
    {
        $transaction->ledgerEntries()->delete();

        if ($transaction->status !== TransactionStatus::Completed || $transaction->legacy_migrated_at !== null) {
            return;
        }

        if ($transaction->type === TransactionType::Income) {
            $this->recordIncomeCustody($transaction);

            return;
        }

        $this->recordOutcome($transaction);
    }

    private function recordIncomeCustody(Transaction $transaction): void
    {
        $transaction->ledgerEntries()->create([
            'account_id' => $transaction->account_id,
            'user_id' => $transaction->custodian_user_id ?? $transaction->user_id,
            'type' => AccountMemberLedgerEntryType::IncomeCustody,
            'amount' => $transaction->amount,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
    }

    private function recordOutcome(Transaction $transaction): void
    {
        $source = $transaction->payment_source ?? TransactionPaymentSource::AccountFund;
        $paidByUserId = $transaction->paid_by_user_id ?? $transaction->user_id;

        if ($source === TransactionPaymentSource::AccountFund) {
            $this->recordAccountFundOutcome($transaction, $paidByUserId);

            return;
        }

        $positiveCustodians = $this->positiveCustodians($transaction);

        if ($positiveCustodians->isNotEmpty()) {
            $this->recordOutOfPocketOutcomeWithCustody($transaction);

            return;
        }

        $transaction->ledgerEntries()->create([
            'account_id' => $transaction->account_id,
            'user_id' => $paidByUserId,
            'type' => AccountMemberLedgerEntryType::ExpensePaid,
            'amount' => $transaction->amount,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);

        $allocations = $transaction->allocations->isNotEmpty()
            ? $transaction->allocations
            : collect([(object) ['user_id' => $transaction->user_id, 'amount' => $transaction->amount]]);

        foreach ($allocations as $allocation) {
            $transaction->ledgerEntries()->create([
                'account_id' => $transaction->account_id,
                'user_id' => $allocation->user_id,
                'related_user_id' => $paidByUserId,
                'type' => AccountMemberLedgerEntryType::ExpenseShare,
                'amount' => $allocation->amount * -1,
                'description' => $transaction->concept,
                'occurred_at' => $transaction->scheduled_at,
            ]);
        }
    }

    private function recordAccountFundOutcome(Transaction $transaction, int $paidByUserId): void
    {
        $hasExplicitAllocations = $transaction->allocations->isNotEmpty();
        $allocations = $this->responsibilityAllocations($transaction, $paidByUserId);
        $custodies = $this->positiveCustodians($transaction)
            ->mapWithKeys(fn (array $custodian): array => [(int) $custodian['user_id'] => round((float) $custodian['amount'], 2)])
            ->all();
        $positiveCustodyTotal = round(array_sum($custodies), 2);
        $deficit = round(max(0.0, (float) $transaction->amount - $positiveCustodyTotal), 2);
        $coveredAmount = round((float) $transaction->amount - $deficit, 2);
        $coveredByOthers = [];

        foreach ($allocations as $allocation) {
            if ($coveredAmount <= 0.0) {
                break;
            }

            $remaining = round(min((float) $allocation->amount, $coveredAmount), 2);
            $ownUsed = $this->consumeCustody($custodies, (int) $allocation->user_id, $remaining);

            if ($ownUsed > 0.0) {
                $this->createEntry($transaction, (int) $allocation->user_id, AccountMemberLedgerEntryType::AccountFundExpense, $ownUsed * -1);
                $remaining = round($remaining - $ownUsed, 2);
                $coveredAmount = round($coveredAmount - $ownUsed, 2);
            }

            foreach ($custodies as $custodianUserId => $available) {
                if ($remaining <= 0.0 || $coveredAmount <= 0.0) {
                    break;
                }

                if ((int) $custodianUserId === (int) $allocation->user_id || $available <= 0.0) {
                    continue;
                }

                $used = $this->consumeCustody($custodies, (int) $custodianUserId, min($remaining, $coveredAmount));

                if ($used <= 0.0) {
                    continue;
                }

                $this->createEntry($transaction, (int) $custodianUserId, AccountMemberLedgerEntryType::AccountFundExpense, $used * -1);

                if ($deficit <= 0.0 && ! $hasExplicitAllocations) {
                    $coveredByOthers[(int) $custodianUserId] = round(($coveredByOthers[(int) $custodianUserId] ?? 0.0) + $used, 2);
                }

                $remaining = round($remaining - $used, 2);
                $coveredAmount = round($coveredAmount - $used, 2);
            }
        }

        if ($deficit > 0.0) {
            $receiverUserId = $this->primaryCustodianUserId($transaction, $paidByUserId);

            foreach ($this->deficitAmounts($allocations, $deficit) as $userId => $amount) {
                $this->createEntry(
                    transaction: $transaction,
                    userId: (int) $userId,
                    type: AccountMemberLedgerEntryType::AccountDeficitShare,
                    amount: $amount * -1,
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
            ->mapWithKeys(fn (array $custodian): array => [(int) $custodian['user_id'] => round((float) $custodian['amount'], 2)])
            ->all();

        foreach ($allocations as $allocation) {
            $remaining = (float) $allocation->amount;
            $ownAmount = $this->consumeCustody($custodies, (int) $allocation->user_id, $remaining);

            if ($ownAmount > 0.0) {
                $this->createCustodyReimbursementDue($transaction, (int) $allocation->user_id, (int) $allocation->user_id, $ownAmount);
                $remaining = round($remaining - $ownAmount, 2);
            }

            foreach ($custodies as $custodianUserId => $available) {
                if ($remaining <= 0.0) {
                    break;
                }

                if ($available <= 0.0) {
                    continue;
                }

                $used = $this->consumeCustody($custodies, (int) $custodianUserId, $remaining);

                if ($used <= 0.0) {
                    continue;
                }

                $this->createCustodyReimbursementDue($transaction, (int) $custodianUserId, (int) $allocation->user_id, $used);
                $remaining = round($remaining - $used, 2);
            }
        }
    }

    private function createCustodyReimbursementDue(Transaction $transaction, int $fromUserId, int $toUserId, float $amount): void
    {
        if ($amount <= 0.0) {
            return;
        }

        if ($fromUserId === $toUserId) {
            $this->createEntry($transaction, $fromUserId, AccountMemberLedgerEntryType::CustodyReimbursementDue, $amount * -1, $toUserId);

            return;
        }

        $this->createEntry($transaction, $fromUserId, AccountMemberLedgerEntryType::CustodyReimbursementDue, $amount * -1, $toUserId);
        $this->createEntry($transaction, $toUserId, AccountMemberLedgerEntryType::CustodyReimbursementDue, $amount, $fromUserId);
    }

    private function createEntry(
        Transaction $transaction,
        int $userId,
        AccountMemberLedgerEntryType $type,
        float $amount,
        ?int $relatedUserId = null,
    ): AccountMemberLedgerEntry {
        return $transaction->ledgerEntries()->create([
            'account_id' => $transaction->account_id,
            'user_id' => $userId,
            'related_user_id' => $relatedUserId,
            'type' => $type,
            'amount' => round($amount, 2),
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
    }

    private function consumeCustody(array &$custodies, int $userId, float $amount): float
    {
        $available = round((float) ($custodies[$userId] ?? 0.0), 2);
        $used = round(min($available, $amount), 2);

        if ($used <= 0.0) {
            return 0.0;
        }

        $custodies[$userId] = round($available - $used, 2);

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

        if ($custodians->isNotEmpty() || round((float) $account->balance, 2) <= 0.0) {
            return $custodians;
        }

        return collect([[
            'user_id' => (int) $account->user_id,
            'amount' => round((float) $account->balance, 2),
        ]]);
    }

    private function responsibilityAllocations(Transaction $transaction, int $fallbackUserId): Collection
    {
        return $transaction->allocations->isNotEmpty()
            ? $transaction->allocations
            : collect([(object) ['user_id' => $fallbackUserId, 'amount' => $transaction->amount, 'percentage' => 100.0]]);
    }

    private function deficitAmounts(Collection $allocations, float $deficit): array
    {
        $total = round((float) $allocations->sum('amount'), 2);

        if ($total <= 0.0) {
            return [];
        }

        $allocated = 0.0;
        $lastIndex = $allocations->count() - 1;
        $amounts = [];

        foreach ($allocations->values() as $index => $allocation) {
            $amount = $index === $lastIndex
                ? round($deficit - $allocated, 2)
                : round($deficit * ((float) $allocation->amount / $total), 2);

            $allocated = round($allocated + $amount, 2);
            $amounts[(int) $allocation->user_id] = round(($amounts[(int) $allocation->user_id] ?? 0.0) + $amount, 2);
        }

        return $amounts;
    }
}
