<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use function app;

use App\Services\PersonalMoney\CapturePersonalMoneyReimbursements;
use App\Services\Transaction\SyncAccountMemberLedger;

use function array_filter;
use function array_values;
use function auth;
use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAccountMemberTransfer
{
    public function __construct(
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
        private readonly SyncAccountMemberLedger $syncAccountMemberLedger,
        private readonly CapturePersonalMoneyReimbursements $capturePersonalMoneyReimbursements,
    ) {
    }

    public function execute(Account $account, array $payload): array
    {
        $amount = Decimal::normalize($payload['amount']);
        $occurredAt = isset($payload['occurred_at'])
            ? Carbon::parse($payload['occurred_at'])
            : Carbon::now();
        $description = $payload['description'] ?? 'Transferencia interna';

        return DB::transaction(function () use ($account, $payload, $amount, $occurredAt, $description): array {
            Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $actionType = $payload['action_type'] ?? 'user_to_user';

            if ($account->virtualCustodians()->exists()) {
                $this->validateVirtualAccountTransfer($account, $payload, $amount, $actionType);
                $virtualEntries = $this->virtualCustodySettlementEntries($account, $payload, $amount, $occurredAt, $description);
                if (null !== $virtualEntries) {
                    $this->capturePersonalMoneyReimbursements->execute($account, $payload, $virtualEntries);

                    return $virtualEntries;
                }
            }

            if ('user_to_account' === $actionType) {
                return $this->createAccountDeficitPayment(
                    account: $account,
                    fromUserId: (int) $payload['from_user_id'],
                    toUserId: (int) $payload['to_user_id'],
                    amount: $amount,
                    description: $description,
                    occurredAt: $occurredAt,
                );
            }

            $entries = 'custody_to_user' === $actionType && (int) $payload['from_user_id'] === (int) $payload['to_user_id']
                ? $this->createSelfSettlementEntries(
                    account: $account,
                    userId: (int) $payload['from_user_id'],
                    amount: $amount,
                    description: $description,
                    occurredAt: $occurredAt,
                )
                : $this->createSettlementEntries(
                    account: $account,
                    fromUserId: (int) $payload['from_user_id'],
                    toUserId: (int) $payload['to_user_id'],
                    amount: $amount,
                    description: $description,
                    occurredAt: $occurredAt,
                    types: 'custody_to_user' === $actionType
                        ? [AccountMemberLedgerEntryType::CustodyReimbursementDue, AccountMemberLedgerEntryType::SettlementTransfer]
                        : null,
                );

            if ('custody_to_user' === $actionType) {
                $entries[] = $account->memberLedgerEntries()->create([
                    'user_id'         => (int) $payload['from_user_id'],
                    'related_user_id' => (int) $payload['to_user_id'],
                    'type'            => AccountMemberLedgerEntryType::CustodyReimbursementPayment,
                    'amount'          => Decimal::multiply($amount, -1),
                    'description'     => $description,
                    'occurred_at'     => $occurredAt,
                ]);
            }

            if ('user_to_user' === $actionType) {
                $this->createNegativeBalanceSettlementIncomes(
                    account: $account,
                    entries: $entries,
                    description: $description,
                    occurredAt: $occurredAt,
                );
            }

            $this->capturePersonalMoneyReimbursements->execute($account, $payload, $entries);

            return $entries;
        });
    }
    private const ROUNDING_SETTLEMENT_TOLERANCE = '0.1';

    private function validateVirtualAccountTransfer(Account $account, array $payload, string $amount, string $actionType): void
    {
        $pending = app(BuildAccountMemberSummary::class)->execute($account)['pending_reimbursements'];
        $available = Decimal::sum(collect($pending)->filter(fn (array $item): bool => (int) $item['from_user_id'] === (int) $payload['from_user_id']
            && (int) $item['to_user_id'] === (int) $payload['to_user_id']
            && ($item['action_type'] ?? 'user_to_user') === $actionType), 'amount');

        if (Decimal::compare($amount, $available) > 0) {
            throw ValidationException::withMessages(['amount' => 'El importe supera el reembolso pendiente.']);
        }

        if ('custody_to_user' !== $actionType) {
            return;
        }

        $custody = app(BuildAccountCustodySummary::class)->execute($account)->firstWhere('user_id', (int) $payload['from_user_id']);
        if (Decimal::compare($amount, $custody['amount'] ?? '0.00') > 0) {
            throw ValidationException::withMessages(['amount' => 'La custodia disponible es insuficiente.']);
        }
    }

    private function virtualCustodySettlementEntries(Account $account, array $payload, string $amount, Carbon $occurredAt, string $description): ?array
    {
        if (($payload['action_type'] ?? '') !== 'custody_to_user') {
            return null;
        }

        $fromId = (int) $payload['from_user_id'];
        $toId = (int) $payload['to_user_id'];
        $items = $account->memberLedgerEntries()
            ->selectRaw('transaction_id, round(-sum(amount), 2) as open_amount')
            ->where('user_id', $fromId)->where('related_user_id', $toId)
            ->whereIn('type', [AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue, AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer])
            ->whereNotNull('transaction_id')->groupBy('transaction_id')
            ->havingRaw('sum(amount) < 0')->orderBy('transaction_id')->get();

        if ($items->isEmpty()) {
            return null;
        }

        $entries = [];
        $remaining = $amount;
        foreach ($items as $item) {
            $paid = Decimal::min($remaining, Decimal::normalize($item->open_amount));
            if (Decimal::compare($paid, 0) <= 0) {
                break;
            }
            $entries[] = $account->memberLedgerEntries()->create([
                'user_id' => $fromId, 'related_user_id' => $toId, 'transaction_id' => $item->transaction_id,
                'type'    => AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
                'amount'  => $paid, 'description' => $description, 'occurred_at' => $occurredAt,
            ]);
            if ($fromId !== $toId) {
                $entries[] = $account->memberLedgerEntries()->create([
                    'user_id' => $toId, 'related_user_id' => $fromId, 'transaction_id' => $item->transaction_id,
                    'type'    => AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
                    'amount'  => Decimal::negate($paid), 'description' => $description, 'occurred_at' => $occurredAt,
                ]);
            }
            $remaining = Decimal::subtract($remaining, $paid);
        }

        if (Decimal::compare($remaining, '0.00') > 0) {
            $legacyEntries = $fromId === $toId
                ? $this->createSelfSettlementEntries($account, $fromId, $remaining, $description, $occurredAt)
                : $this->createSettlementEntries(
                    $account, $fromId, $toId, $remaining, $description, $occurredAt,
                    [AccountMemberLedgerEntryType::CustodyReimbursementDue, AccountMemberLedgerEntryType::SettlementTransfer],
                );
            $entries = [...$entries, ...$legacyEntries];
        }
        $entries[] = $account->memberLedgerEntries()->create([
            'user_id' => $fromId, 'related_user_id' => $toId,
            'type'    => AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            'amount'  => Decimal::negate($amount), 'description' => $description, 'occurred_at' => $occurredAt,
        ]);

        return $entries;
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createAccountDeficitPayment(
        Account $account,
        int $fromUserId,
        int $toUserId,
        string $amount,
        string $description,
        Carbon $occurredAt,
    ): array {
        $income = new Transaction();
        $income->type = TransactionType::Income;
        $income->status = TransactionStatus::Completed;
        $income->concept = $description;
        $income->amount = $amount;
        $income->percentage = '100.00';
        $income->account_id = $account->id;
        $income->custodian_user_id = $toUserId;
        $income->scheduled_at = $occurredAt;
        $income->user_id = auth()->id() ?? $fromUserId;
        $income->save();

        $this->syncAccountMemberLedger->execute($income);
        $this->recalculateAccountBalance->execute($account);

        $entries = [$income->ledgerEntries()->first()];
        $items = $this->openTransactionDebts($account, $fromUserId, [
            AccountMemberLedgerEntryType::AccountDeficitShare,
            AccountMemberLedgerEntryType::AccountDeficitPayment,
        ]);
        $runningTotal = '0.00';

        foreach ($items as $item) {
            if (Decimal::compare($runningTotal, $amount) >= 0) {
                break;
            }

            $itemAmount = Decimal::min(Decimal::normalize($item->open_amount), Decimal::subtract($amount, $runningTotal));

            if (Decimal::compare($itemAmount, '0.00') <= 0) {
                continue;
            }

            $entries[] = $account->memberLedgerEntries()->create([
                'user_id'         => $fromUserId,
                'transaction_id'  => (int) $item->transaction_id,
                'related_user_id' => $toUserId,
                'type'            => AccountMemberLedgerEntryType::AccountDeficitPayment,
                'amount'          => $itemAmount,
                'description'     => $description,
                'occurred_at'     => $occurredAt,
            ]);

            $runningTotal = Decimal::add($runningTotal, $itemAmount);
        }

        return array_values(array_filter($entries));
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createSelfSettlementEntries(
        Account $account,
        int $userId,
        string $amount,
        string $description,
        Carbon $occurredAt,
    ): array {
        $items = $this->openTransactionDebts($account, $userId, [
            AccountMemberLedgerEntryType::CustodyReimbursementDue,
            AccountMemberLedgerEntryType::SettlementTransfer,
        ]);
        $entries = [];
        $runningTotal = '0.00';

        foreach ($items as $item) {
            if (Decimal::compare($runningTotal, $amount) >= 0) {
                break;
            }

            $itemAmount = Decimal::min(Decimal::normalize($item->open_amount), Decimal::subtract($amount, $runningTotal));

            if (Decimal::compare($itemAmount, '0.00') <= 0) {
                continue;
            }

            $entries[] = $this->createSettlementEntry(
                $account,
                $userId,
                $userId,
                $itemAmount,
                $description,
                $occurredAt,
                (int) $item->transaction_id,
            );
            $runningTotal = Decimal::add($runningTotal, $itemAmount);
        }

        return $entries;
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createSettlementEntries(
        Account $account,
        int $fromUserId,
        int $toUserId,
        string $amount,
        string $description,
        Carbon $occurredAt,
        ?array $types = null,
    ): array {
        $items = $this->openTransactionDebts($account, $fromUserId, $types, $toUserId);

        if ($items->isEmpty()) {
            return [
                $this->createSettlementEntry($account, $fromUserId, $toUserId, $amount, $description, $occurredAt),
                $this->createSettlementEntry($account, $toUserId, $fromUserId, Decimal::multiply($amount, -1), $description, $occurredAt),
            ];
        }

        $entries = [];
        $runningTotal = '0.00';
        $transactionDebtTotal = Decimal::sum($items, 'open_amount');
        $amountToAllocate = $this->shouldCloseAllTransactionDebts($amount, $transactionDebtTotal)
            ? $transactionDebtTotal
            : $amount;

        foreach ($items as $item) {
            $remaining = Decimal::subtract($amountToAllocate, $runningTotal);

            if (Decimal::compare($remaining, '0.00') <= 0) {
                break;
            }

            $itemAmount = Decimal::min(Decimal::normalize($item->open_amount), $remaining);

            if (Decimal::compare($itemAmount, '0.00') <= 0) {
                continue;
            }

            $entries[] = $this->createSettlementEntry(
                $account,
                $fromUserId,
                $toUserId,
                $itemAmount,
                $description,
                $occurredAt,
                (int) $item->transaction_id,
            );
            $entries[] = $this->createSettlementEntry(
                $account,
                $toUserId,
                $fromUserId,
                Decimal::multiply($itemAmount, -1),
                $description,
                $occurredAt,
                (int) $item->transaction_id,
            );

            $runningTotal = Decimal::add($runningTotal, $itemAmount);
        }

        $unallocatedAmount = Decimal::subtract($amount, $runningTotal);

        if (Decimal::compare(Decimal::abs($unallocatedAmount), '0.00') > 0) {
            $entries[] = $this->createSettlementEntry($account, $fromUserId, $toUserId, $unallocatedAmount, $description, $occurredAt);
            $entries[] = $this->createSettlementEntry($account, $toUserId, $fromUserId, Decimal::multiply($unallocatedAmount, -1), $description, $occurredAt);
        }

        return $entries;
    }

    /**
     * @return Collection<int, AccountMemberLedgerEntry>
     */
    private function openTransactionDebts(Account $account, int $fromUserId, ?array $types = null, ?int $toUserId = null): Collection
    {
        return $account->memberLedgerEntries()
            ->selectRaw('transaction_id, round(abs(sum(amount)), 2) as open_amount')
            ->where('user_id', $fromUserId)
            ->when(null !== $toUserId && $account->virtualCustodians()->exists(), fn ($query) => $query->where('related_user_id', $toUserId))
            ->whereNotNull('transaction_id')
            ->whereIn('type', $types ?? [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
            ])
            ->groupBy('transaction_id')
            ->havingRaw('sum(amount) < 0')
            ->orderByDesc('transaction_id')
            ->get();
    }

    private function shouldCloseAllTransactionDebts(string $amount, string $transactionDebtTotal): bool
    {
        if (Decimal::compare($transactionDebtTotal, '0.00') <= 0) {
            return false;
        }

        return Decimal::compare(Decimal::abs(Decimal::subtract($transactionDebtTotal, $amount)), self::ROUNDING_SETTLEMENT_TOLERANCE) <= 0;
    }

    private function createSettlementEntry(
        Account $account,
        int $userId,
        int $relatedUserId,
        string $amount,
        string $description,
        Carbon $occurredAt,
        ?int $transactionId = null,
    ): AccountMemberLedgerEntry {
        return $account->memberLedgerEntries()->create([
            'user_id'         => $userId,
            'transaction_id'  => $transactionId,
            'related_user_id' => $relatedUserId,
            'type'            => AccountMemberLedgerEntryType::SettlementTransfer,
            'amount'          => $amount,
            'description'     => $description,
            'occurred_at'     => $occurredAt,
        ]);
    }

    /**
     * @param array<int, AccountMemberLedgerEntry> $entries
     */
    private function createNegativeBalanceSettlementIncomes(
        Account $account,
        array $entries,
        string $description,
        Carbon $occurredAt,
    ): void {
        $remainingDeficit = Decimal::abs(Decimal::min(Decimal::normalize($account->fresh()->balance), '0.00'));

        if (Decimal::compare($remainingDeficit, '0.00') <= 0) {
            return;
        }

        $createdTotal = '0.00';

        foreach ($entries as $entry) {
            if (Decimal::compare($remainingDeficit, '0.00') <= 0) {
                break;
            }

            if (AccountMemberLedgerEntryType::SettlementTransfer !== $entry->type
                || Decimal::compare(Decimal::normalize($entry->amount), '0.00') <= 0
                || null === $entry->transaction_id
            ) {
                continue;
            }

            $incomeAmount = Decimal::min(Decimal::normalize($entry->amount), $remainingDeficit);

            if (Decimal::compare($incomeAmount, '0.00') <= 0) {
                continue;
            }

            $parentTransaction = Transaction::withoutGlobalScopes()->find($entry->transaction_id);

            if (! $parentTransaction) {
                continue;
            }

            $income = new Transaction();
            $income->parent_transaction_id = $parentTransaction->id;
            $income->type = TransactionType::Income;
            $income->status = TransactionStatus::Completed;
            $income->concept = $description . ': ' . $parentTransaction->concept;
            $income->amount = $incomeAmount;
            $income->percentage = '100.00';
            $income->account_id = $account->id;
            $income->user_id = $entry->user_id;
            $income->paid_by_user_id = null;
            $income->custodian_user_id = null;
            $income->payment_source = null;
            $income->scheduled_at = $occurredAt;
            $income->financial_goal_id = null;
            $income->legacy_migrated_at = Carbon::now();
            $income->save();

            $createdTotal = Decimal::add($createdTotal, $incomeAmount);
            $remainingDeficit = Decimal::subtract($remainingDeficit, $incomeAmount);
        }

        if (Decimal::compare($createdTotal, '0.00') > 0) {
            $account->newQuery()
                ->whereKey($account->id)
                ->increment('balance', $createdTotal);
        }
    }
}
