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
            ->selectRaw('user_id, sum(amount) as amount')
            ->groupBy('user_id')
            ->pluck('amount', 'user_id');

        return $users
            ->map(fn (User $user): array => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'amount' => (float) ($amounts[$user->id] ?? 0.0),
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
