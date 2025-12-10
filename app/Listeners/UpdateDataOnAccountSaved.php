<?php

namespace App\Listeners;

use App\Events\AccountSaved;

class UpdateDataOnAccountSaved
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AccountSaved $event): void
    {
        $account = $event->account;
        if ($account->isCreditCard()) {
            $event->account->updateBalance();
        }
    }
}
