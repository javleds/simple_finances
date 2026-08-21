<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Services\Transaction\SyncAccountMemberLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterAccountMemberTransfer
{
    private const ROUNDING_SETTLEMENT_TOLERANCE = 0.10;

    public function __construct(
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
        private readonly SyncAccountMemberLedger $syncAccountMemberLedger,
    ) {}

    public function execute(Account $account, array $payload): array
    {
        $amount = round((float) $payload['amount'], 2);
        $occurredAt = isset($payload['occurred_at'])
            ? Carbon::parse($payload['occurred_at'])
            : Carbon::now();
        $description = $payload['description'] ?? 'Transferencia interna';

        return DB::transaction(function () use ($account, $payload, $amount, $occurredAt, $description): array {
            $actionType = $payload['action_type'] ?? 'user_to_user';

            if ($actionType === 'user_to_account') {
                return $this->createAccountDeficitPayment(
                    account: $account,
                    fromUserId: (int) $payload['from_user_id'],
                    toUserId: (int) $payload['to_user_id'],
                    amount: $amount,
                    description: $description,
                    occurredAt: $occurredAt,
                );
            }

            $entries = $actionType === 'custody_to_user' && (int) $payload['from_user_id'] === (int) $payload['to_user_id']
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
                    types: $actionType === 'custody_to_user'
                        ? [AccountMemberLedgerEntryType::CustodyReimbursementDue, AccountMemberLedgerEntryType::SettlementTransfer]
                        : null,
                );

            if ($actionType === 'custody_to_user') {
                $entries[] = $account->memberLedgerEntries()->create([
                    'user_id' => (int) $payload['from_user_id'],
                    'related_user_id' => (int) $payload['to_user_id'],
                    'type' => AccountMemberLedgerEntryType::CustodyReimbursementPayment,
                    'amount' => $amount * -1,
                    'description' => $description,
                    'occurred_at' => $occurredAt,
                ]);
            }

            if ($actionType === 'user_to_user') {
                $this->createNegativeBalanceSettlementIncomes(
                    account: $account,
                    entries: $entries,
                    description: $description,
                    occurredAt: $occurredAt,
                );
            }

            return $entries;
        });
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createAccountDeficitPayment(
        Account $account,
        int $fromUserId,
        int $toUserId,
        float $amount,
        string $description,
        Carbon $occurredAt,
    ): array {
        $income = new Transaction;
        $income->type = TransactionType::Income;
        $income->status = TransactionStatus::Completed;
        $income->concept = $description;
        $income->amount = $amount;
        $income->percentage = 100.0;
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
        $runningTotal = 0.0;

        foreach ($items as $item) {
            if ($runningTotal >= $amount) {
                break;
            }

            $itemAmount = round(min((float) $item->open_amount, $amount - $runningTotal), 2);

            if ($itemAmount <= 0.0) {
                continue;
            }

            $entries[] = $account->memberLedgerEntries()->create([
                'user_id' => $fromUserId,
                'transaction_id' => (int) $item->transaction_id,
                'related_user_id' => $toUserId,
                'type' => AccountMemberLedgerEntryType::AccountDeficitPayment,
                'amount' => $itemAmount,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            $runningTotal = round($runningTotal + $itemAmount, 2);
        }

        return array_values(array_filter($entries));
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createSelfSettlementEntries(
        Account $account,
        int $userId,
        float $amount,
        string $description,
        Carbon $occurredAt,
    ): array {
        $items = $this->openTransactionDebts($account, $userId, [
            AccountMemberLedgerEntryType::CustodyReimbursementDue,
            AccountMemberLedgerEntryType::SettlementTransfer,
        ]);
        $entries = [];
        $runningTotal = 0.0;

        foreach ($items as $item) {
            if ($runningTotal >= $amount) {
                break;
            }

            $itemAmount = round(min((float) $item->open_amount, $amount - $runningTotal), 2);

            if ($itemAmount <= 0.0) {
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
            $runningTotal = round($runningTotal + $itemAmount, 2);
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
        float $amount,
        string $description,
        Carbon $occurredAt,
        ?array $types = null,
    ): array {
        $items = $this->openTransactionDebts($account, $fromUserId, $types);

        if ($items->isEmpty()) {
            return [
                $this->createSettlementEntry($account, $fromUserId, $toUserId, $amount, $description, $occurredAt),
                $this->createSettlementEntry($account, $toUserId, $fromUserId, $amount * -1, $description, $occurredAt),
            ];
        }

        $entries = [];
        $runningTotal = 0.0;
        $transactionDebtTotal = round((float) $items->sum('open_amount'), 2);
        $amountToAllocate = $this->shouldCloseAllTransactionDebts($amount, $transactionDebtTotal)
            ? $transactionDebtTotal
            : $amount;

        foreach ($items as $item) {
            $remaining = round($amountToAllocate - $runningTotal, 2);

            if ($remaining <= 0.0) {
                break;
            }

            $itemAmount = round(min((float) $item->open_amount, $remaining), 2);

            if ($itemAmount <= 0.0) {
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
                $itemAmount * -1,
                $description,
                $occurredAt,
                (int) $item->transaction_id,
            );

            $runningTotal = round($runningTotal + $itemAmount, 2);
        }

        $unallocatedAmount = round($amount - $runningTotal, 2);

        if (abs($unallocatedAmount) > 0.001) {
            $entries[] = $this->createSettlementEntry($account, $fromUserId, $toUserId, $unallocatedAmount, $description, $occurredAt);
            $entries[] = $this->createSettlementEntry($account, $toUserId, $fromUserId, $unallocatedAmount * -1, $description, $occurredAt);
        }

        return $entries;
    }

    /**
     * @return Collection<int, AccountMemberLedgerEntry>
     */
    private function openTransactionDebts(Account $account, int $fromUserId, ?array $types = null): Collection
    {
        return $account->memberLedgerEntries()
            ->selectRaw('transaction_id, round(abs(sum(amount)), 2) as open_amount')
            ->where('user_id', $fromUserId)
            ->whereNotNull('transaction_id')
            ->whereIn('type', $types ?? [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
            ])
            ->groupBy('transaction_id')
            ->havingRaw('sum(amount) < -0.001')
            ->orderByDesc('transaction_id')
            ->get();
    }

    private function shouldCloseAllTransactionDebts(float $amount, float $transactionDebtTotal): bool
    {
        if ($transactionDebtTotal <= 0.0) {
            return false;
        }

        return abs(round($transactionDebtTotal - $amount, 2)) <= self::ROUNDING_SETTLEMENT_TOLERANCE;
    }

    private function createSettlementEntry(
        Account $account,
        int $userId,
        int $relatedUserId,
        float $amount,
        string $description,
        Carbon $occurredAt,
        ?int $transactionId = null,
    ): AccountMemberLedgerEntry {
        return $account->memberLedgerEntries()->create([
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'related_user_id' => $relatedUserId,
            'type' => AccountMemberLedgerEntryType::SettlementTransfer,
            'amount' => $amount,
            'description' => $description,
            'occurred_at' => $occurredAt,
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
        $remainingDeficit = round(abs(min((float) $account->fresh()->balance, 0.0)), 2);

        if ($remainingDeficit <= 0.0) {
            return;
        }

        $createdTotal = 0.0;

        foreach ($entries as $entry) {
            if ($remainingDeficit <= 0.0) {
                break;
            }

            if ($entry->type !== AccountMemberLedgerEntryType::SettlementTransfer
                || (float) $entry->amount <= 0.0
                || $entry->transaction_id === null
            ) {
                continue;
            }

            $incomeAmount = round(min((float) $entry->amount, $remainingDeficit), 2);

            if ($incomeAmount <= 0.0) {
                continue;
            }

            $parentTransaction = Transaction::withoutGlobalScopes()->find($entry->transaction_id);

            if (! $parentTransaction) {
                continue;
            }

            $income = new Transaction;
            $income->parent_transaction_id = $parentTransaction->id;
            $income->type = TransactionType::Income;
            $income->status = TransactionStatus::Completed;
            $income->concept = $description.': '.$parentTransaction->concept;
            $income->amount = $incomeAmount;
            $income->percentage = 100.0;
            $income->account_id = $account->id;
            $income->user_id = $entry->user_id;
            $income->paid_by_user_id = null;
            $income->custodian_user_id = null;
            $income->payment_source = null;
            $income->scheduled_at = $occurredAt;
            $income->financial_goal_id = null;
            $income->legacy_migrated_at = Carbon::now();
            $income->save();

            $createdTotal = round($createdTotal + $incomeAmount, 2);
            $remainingDeficit = round($remainingDeficit - $incomeAmount, 2);
        }

        if ($createdTotal > 0.0) {
            $account->newQuery()
                ->whereKey($account->id)
                ->increment('balance', $createdTotal);
        }
    }

}
