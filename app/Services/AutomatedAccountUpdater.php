<?php

namespace App\Services;

use App\Models\Account;
use App\Services\Accounts\RecalculateAccountBalance;
use Carbon\Carbon;

class AutomatedAccountUpdater
{
    public function __construct(private readonly RecalculateAccountBalance $recalculateAccountBalance) {}

    public function handle(): void
    {
        $accounts = Account::withoutGlobalScopes()->get();
        $now = Carbon::now();

        foreach ($accounts as $account) {
            if (! $account->isCreditCard()) {
                continue;
            }

            if ($now > $account->next_cutoff_date) {
                $account->next_cutoff_date = $account->next_cutoff_date->addMonth()->clone();
                $account->save();

                $this->recalculateAccountBalance->execute($account);
            }
        }
    }
}
