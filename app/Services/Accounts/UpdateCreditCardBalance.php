<?php

namespace App\Services\Accounts;

use App\Models\Account;

class UpdateCreditCardBalance
{
    public function execute(Account $account): void
    {
        if ($account->isCreditCard()) {
            $account->updateBalance();
        }
    }
}
