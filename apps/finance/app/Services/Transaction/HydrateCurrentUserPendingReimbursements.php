<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Queries\UserOwnedResources;
use Illuminate\Support\Collection;

class HydrateCurrentUserPendingReimbursements
{
    public function __construct(private readonly UserOwnedResources $userOwnedResources)
    {
    }

    /**
     * @param iterable<int, Transaction> $transactions
     */
    public function execute(iterable $transactions, int $userId): void
    {
        $transactions = collect($transactions)->values();
        $transactionIds = $transactions
            ->pluck('id')
            ->filter()
            ->map(fn (int | string $id): int => (int) $id)
            ->values();

        if ($transactionIds->isEmpty()) {
            return;
        }

        $openAmountsByTransactionId = $this->openAmounts($transactionIds, $userId);

        $transactions->each(function (Transaction $transaction) use ($openAmountsByTransactionId): void {
            $openAmount = Decimal::normalize($openAmountsByTransactionId->get($transaction->id, '0.00'));

            $transaction->setAttribute(
                'current_user_pending_reimbursement_amount',
                Decimal::compare($openAmount, 0) < 0 ? Decimal::abs($openAmount) : '0.00',
            );

            $transaction->setAttribute(
                'current_user_receivable_reimbursement_amount',
                Decimal::compare($openAmount, 0) > 0 ? $openAmount : '0.00',
            );
        });
    }

    /**
     * @param Collection<int, int> $transactionIds
     *
     * @return Collection<int, string>
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
                AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue,
                AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
                AccountMemberLedgerEntryType::AccountDeficitShare,
                AccountMemberLedgerEntryType::AccountDeficitPayment,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
            ])
            ->groupBy('transaction_id')
            ->havingRaw('abs(open_amount) > 0')
            ->get()
            ->mapWithKeys(fn (AccountMemberLedgerEntry $entry): array => [
                (int) $entry->transaction_id => Decimal::normalize($entry->open_amount),
            ]);
    }
}
