<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildPendingIncomeByUser
{
    public function execute(Account $account): array
    {
        $users = $this->accountUsers($account);
        $amounts = Transaction::query()
            ->where('account_id', $account->id)
            ->income()
            ->pending()
            ->selectRaw('user_id, sum(amount) as amount, GROUP_CONCAT(id) as transaction_ids')
            ->groupBy('user_id')->get();

        return $users
            ->map(fn (User $user): array => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'amount' => (float) ($amounts->firstWhere('user_id', $user->id)->amount ?? 0.0),
                'transaction_ids' => ($amounts->firstWhere('user_id', $user->id)->transaction_ids ?? '')
                    ? collect(explode(',', $amounts->firstWhere('user_id', $user->id)->transaction_ids))->map(fn ($id) => (int) $id)
                    : [],
            ])
            ->values()
            ->all();
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
