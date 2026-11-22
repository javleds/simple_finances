<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Collection;

class BuildAccountCustodySummary
{
    /**
     * @return Collection<int, array{user_id: int, amount: string}>
     */
    public function execute(Account $account): Collection
    {
        return $account->memberLedgerEntries()
            ->selectRaw('user_id, round(sum(amount), 2) as amount')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::IncomeCustody,
                AccountMemberLedgerEntryType::AccountFundExpense,
                AccountMemberLedgerEntryType::InternalTransfer,
                AccountMemberLedgerEntryType::ManualAdjustment,
                AccountMemberLedgerEntryType::CustodyCorrection,
                AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            ])
            ->groupBy('user_id')
            ->get()
            ->map(fn (AccountMemberLedgerEntry $entry): array => [
                'user_id' => (int) $entry->user_id,
                'amount'  => Decimal::normalize($entry->amount),
            ])
            ->values();
    }

    public function positiveTotal(Account $account): string
    {
        return Decimal::sum($this->positiveCustodians($account), 'amount');
    }

    /**
     * @return Collection<int, array{user_id: int, amount: string}>
     */
    public function positiveCustodians(Account $account): Collection
    {
        return $this->execute($account)
            ->filter(fn (array $item): bool => Decimal::compare($item['amount'], 0) > 0)
            ->sort(fn (array $left, array $right): int => Decimal::compare($right['amount'], $left['amount']))
            ->values();
    }
}
