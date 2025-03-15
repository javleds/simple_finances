<?php

namespace App\Services\Accounts;

use App\Models\Account;

class EnableNotificationsForAccount
{
    public function execute(Account $account): void
    {
        $user = auth()->user();
        $user->notificableAccounts()->attach($account->id);
    }
}
