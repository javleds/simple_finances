<?php

namespace App\Services\Transaction;

use FinSi\FinanceDomain\Queries\UserOwnedResources;
use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Support\Collection;

class HydrateCurrentUserPendingReimbursements
{
    public function __construct(private readonly UserOwnedResources $userOwnedResources) {}

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

        $openAmountsByTransactionId = $this->openAmounts($transactionIds, $userId);

        $transactions->each(function (Transaction $transaction) use ($openAmountsByTransactionId): void {
            $openAmount = round((float) $openAmountsByTransactionId->get($transaction->id, 0.0), 2);

            $transaction->setAttribute(
                'current_user_pending_reimbursement_amount',
                $openAmount < 0 ? abs($openAmount) : 0.0,
            );

            $transaction->setAttribute(
                'current_user_receivable_reimbursement_amount',
                $openAmount > 0 ? $openAmount : 0.0,
            );
        });
    }

    /**
     * @param Collection<int, int> $transactionIds
     * @return Collection<int, float>
     */
    private function openAmounts(Collection $transactionIds, int $userId): Collection
    {
        return $this->userOwnedResources->constrain(AccountMemberLedgerEntry::query(), $userId)
            ->selectRaw('transaction_id, round(sum(amount), 2) as open_amount')
            ->whereIn('transaction_id', $transactionIds)
            ->whereIn('type', [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::AccountDeficitShare,
                AccountMemberLedgerEntryType::AccountDeficitPayment,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
            ])
            ->groupBy('transaction_id')
            ->havingRaw('abs(open_amount) > 0.001')
            ->get()
            ->mapWithKeys(fn (AccountMemberLedgerEntry $entry): array => [
                (int) $entry->transaction_id => round((float) $entry->open_amount, 2),
            ]);
    }
}
