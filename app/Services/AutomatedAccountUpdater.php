<?php

namespace App\Services;

use App\Models\Account;

class AutomatedAccountUpdater
{
    public function handle(): void
    {
        $accounts = Account::withoutGlobalScopes()->all();

        foreach ($accounts as $account) {
            $account->updateBalance();
        }
    }
}
