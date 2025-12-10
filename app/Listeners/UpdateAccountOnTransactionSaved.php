<?php

namespace App\Listeners;

use App\Events\TransactionSaved;
use App\Models\Account;

class UpdateAccountOnTransactionSaved
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
    public function handle(TransactionSaved $event): void
    {
        /** @var Account $account */
        $account = $event->transaction->account;
        $account->updateBalance();
    }
}
