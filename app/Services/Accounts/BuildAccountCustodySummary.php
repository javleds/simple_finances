<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use Illuminate\Support\Collection;

class BuildAccountCustodySummary
{
    /**
     * @return Collection<int, array{user_id: int, amount: float}>
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
            ])
            ->groupBy('user_id')
            ->get()
            ->map(fn (AccountMemberLedgerEntry $entry): array => [
                'user_id' => (int) $entry->user_id,
                'amount' => round((float) $entry->amount, 2),
            ])
            ->values();
    }

    public function positiveTotal(Account $account): float
    {
        return round((float) $this->execute($account)
            ->filter(fn (array $item): bool => $item['amount'] > 0)
            ->sum('amount'), 2);
    }

    /**
     * @return Collection<int, array{user_id: int, amount: float}>
     */
    public function positiveCustodians(Account $account): Collection
    {
        return $this->execute($account)
            ->filter(fn (array $item): bool => $item['amount'] > 0)
            ->sortByDesc('amount')
            ->values();
    }
}
