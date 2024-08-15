<?php

namespace App\Services;

use App\Models\Account;
use Carbon\Carbon;

class AutomatedAccountUpdater
{
    public function handle(): void
    {
        $accounts = Account::withoutGlobalScopes()->get();
        $now = Carbon::now();

        foreach ($accounts as $account) {
            if ($now > $account->next_cutoff_date) {
                $account->next_cutoff_date = $account->next_cutoff_date->addMonth()->clone();
                $account->save();

                $account->updateBalance();
            }
        }
    }
}
