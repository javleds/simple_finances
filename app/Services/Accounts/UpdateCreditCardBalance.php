<?php

namespace App\Services\Accounts;

use App\Models\Account;

class UpdateCreditCardBalance
{
    public function __construct(private readonly RecalculateAccountBalance $recalculateAccountBalance) {}

    public function execute(Account $account): void
    {
        if ($account->isCreditCard()) {
            $this->recalculateAccountBalance->execute($account);
        }
    }
}
