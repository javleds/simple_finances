<?php

namespace App\Services\Transaction;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class HydrateCurrentUserPendingReimbursements
{
    /**
     * @param iterable<int, Transaction> $transactions
     */
    public function execute(iterable $transactions, int $userId): void
    {
        $transactions = collect($transactions)->values();
        $transactionIds = $transactions
            ->pluck('id')
            ->filter()
            ->map(fn (int|string $id): int => (int) $id)
            ->values();

        if ($transactionIds->isEmpty()) {
            return;
        }

        $pendingByTransactionId = $this->pendingAmounts($transactionIds, $userId);

        $transactions->each(function (Transaction $transaction) use ($pendingByTransactionId): void {
            $amount = $pendingByTransactionId->get($transaction->id, 0.0);

            $transaction->setAttribute(
                'current_user_pending_reimbursement_amount',
                round((float) $amount, 2),
            );
        });
    }

    /**
     * @param Collection<int, int> $transactionIds
     * @return Collection<int, float>
     */
    private function pendingAmounts(Collection $transactionIds, int $userId): Collection
    {
        return AccountMemberLedgerEntry::query()
            ->selectRaw('transaction_id, round(sum(amount), 2) as open_amount')
            ->where('user_id', $userId)
            ->whereIn('transaction_id', $transactionIds)
            ->whereIn('type', [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::SettlementTransfer,
            ])
            ->groupBy('transaction_id')
            ->havingRaw('open_amount < -0.001')
            ->get()
            ->mapWithKeys(fn (AccountMemberLedgerEntry $entry): array => [
                (int) $entry->transaction_id => round(abs((float) $entry->open_amount), 2),
            ]);
    }
}
