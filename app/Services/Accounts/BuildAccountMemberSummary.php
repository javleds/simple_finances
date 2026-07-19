<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildAccountMemberSummary
{
    public function execute(Account $account): array
    {
        $users = $this->accountUsers($account);
        $entries = $account->memberLedgerEntries()->get();
        $summary = $users
            ->map(fn (User $user): array => $this->userSummary($user, $entries))
            ->values();

        return [
            'custody_by_user' => $summary
                ->map(fn (array $item): array => [
                    'user_id' => $item['user_id'],
                    'user_name' => $item['user_name'],
                    'amount' => $item['custody_amount'],
                ])
                ->all(),
            'settlements_by_user' => $summary
                ->map(fn (array $item): array => [
                    'user_id' => $item['user_id'],
                    'user_name' => $item['user_name'],
                    'amount' => $item['settlement_amount'],
                ])
                ->all(),
            'pending_reimbursements' => $this->pendingReimbursements($summary),
        ];
    }

    private function userSummary(User $user, Collection $entries): array
    {
        $userEntries = $entries->where('user_id', $user->id);

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'custody_amount' => round($this->custodyAmount($userEntries), 2),
            'settlement_amount' => round($this->settlementAmount($userEntries), 2),
        ];
    }

    private function custodyAmount(Collection $entries): float
    {
        return (float) $entries
            ->filter(fn (AccountMemberLedgerEntry $entry): bool => in_array($entry->type, [
                AccountMemberLedgerEntryType::IncomeCustody,
                AccountMemberLedgerEntryType::AccountFundExpense,
                AccountMemberLedgerEntryType::InternalTransfer,
                AccountMemberLedgerEntryType::ManualAdjustment,
            ], true))
            ->sum('amount');
    }

    private function settlementAmount(Collection $entries): float
    {
        return (float) $entries
            ->filter(fn (AccountMemberLedgerEntry $entry): bool => in_array($entry->type, [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::LegacySettlement,
            ], true))
            ->sum('amount');
    }

    private function pendingReimbursements(Collection $summary): array
    {
        $debtors = $summary
            ->filter(fn (array $item): bool => $item['settlement_amount'] < 0)
            ->map(fn (array $item): array => [...$item, 'open_amount' => abs($item['settlement_amount'])])
            ->values();
        $creditors = $summary
            ->filter(fn (array $item): bool => $item['settlement_amount'] > 0)
            ->map(fn (array $item): array => [...$item, 'open_amount' => $item['settlement_amount']])
            ->values();
        $items = [];

        foreach ($debtors as &$debtor) {
            foreach ($creditors as &$creditor) {
                if ($debtor['open_amount'] <= 0 || $creditor['open_amount'] <= 0) {
                    continue;
                }

                $amount = round(min($debtor['open_amount'], $creditor['open_amount']), 2);

                if ($amount <= 0) {
                    continue;
                }

                $items[] = [
                    'from_user_id' => $debtor['user_id'],
                    'from_user_name' => $debtor['user_name'],
                    'to_user_id' => $creditor['user_id'],
                    'to_user_name' => $creditor['user_name'],
                    'amount' => $amount,
                ];

                $debtor['open_amount'] = round($debtor['open_amount'] - $amount, 2);
                $creditor['open_amount'] = round($creditor['open_amount'] - $amount, 2);
            }
        }

        return $items;
    }

    private function accountUsers(Account $account): Collection
    {
        $users = $account->users()
            ->orderBy('users.id')
            ->get();

        if ($users->isNotEmpty()) {
            return $users;
        }

        return User::query()
            ->where('id', $account->user_id)
            ->get();
    }
}
