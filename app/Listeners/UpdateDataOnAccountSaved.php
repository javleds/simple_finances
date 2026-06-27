<?php

namespace App\Listeners;

use App\Events\AccountSaved;
use App\Services\Accounts\RecalculateAccountBalance;

class UpdateDataOnAccountSaved
{
    public function __construct(private readonly RecalculateAccountBalance $recalculateAccountBalance) {}

    /**
     * Handle the event.
     */
    public function handle(AccountSaved $event): void
    {
        $account = $event->account;
        if ($account->isCreditCard()) {
            $this->recalculateAccountBalance->execute($account);
        }
    }
}
