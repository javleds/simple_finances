<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterAccountMemberTransfer
{
    private const ROUNDING_SETTLEMENT_TOLERANCE = 0.10;

    public function execute(Account $account, array $payload): array
    {
        $amount = round((float) $payload['amount'], 2);
        $occurredAt = isset($payload['occurred_at'])
            ? Carbon::parse($payload['occurred_at'])
            : Carbon::now();
        $description = $payload['description'] ?? 'Transferencia interna';

        return DB::transaction(function () use ($account, $payload, $amount, $occurredAt, $description): array {
            $custodyFromEntry = $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['from_user_id'],
                'related_user_id' => (int) $payload['to_user_id'],
                'type' => AccountMemberLedgerEntryType::InternalTransfer,
                'amount' => $amount * -1,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            $custodyToEntry = $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['to_user_id'],
                'related_user_id' => (int) $payload['from_user_id'],
                'type' => AccountMemberLedgerEntryType::InternalTransfer,
                'amount' => $amount,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            $settlementEntries = $this->createSettlementEntries(
                account: $account,
                fromUserId: (int) $payload['from_user_id'],
                toUserId: (int) $payload['to_user_id'],
                amount: $amount,
                description: $description,
                occurredAt: $occurredAt,
            );

            $this->createBalanceRecoveryTransaction(
                account: $account,
                fromUserId: (int) $payload['from_user_id'],
                toUserId: (int) $payload['to_user_id'],
                amount: $amount,
                description: $description,
                occurredAt: $occurredAt,
            );

            return [$custodyFromEntry, $custodyToEntry, ...$settlementEntries];
        });
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
    ): array {
        $items = $this->openTransactionDebts($account, $fromUserId);

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
    private function openTransactionDebts(Account $account, int $fromUserId): Collection
    {
        return $account->memberLedgerEntries()
            ->selectRaw('transaction_id, round(abs(sum(amount)), 2) as open_amount')
            ->where('user_id', $fromUserId)
            ->whereNotNull('transaction_id')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::SettlementTransfer,
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

    private function createBalanceRecoveryTransaction(
        Account $account,
        int $fromUserId,
        int $toUserId,
        float $amount,
        string $description,
        Carbon $occurredAt,
    ): ?Transaction {
        $balance = round((float) $account->fresh()->balance, 2);

        if ($balance >= 0.0) {
            return null;
        }

        $recoveryAmount = round(min($amount, abs($balance)), 2);

        if ($recoveryAmount <= 0.0) {
            return null;
        }

        $transaction = new Transaction;
        $transaction->account_id = $account->id;
        $transaction->user_id = $fromUserId;
        $transaction->custodian_user_id = $toUserId;
        $transaction->type = TransactionType::Income;
        $transaction->status = TransactionStatus::Completed;
        $transaction->concept = $description;
        $transaction->amount = $recoveryAmount;
        $transaction->percentage = 100.0;
        $transaction->scheduled_at = $occurredAt;
        $transaction->financial_goal_id = null;
        $transaction->save();

        app(RecalculateAccountBalance::class)->execute($account);

        return $transaction;
    }
}
