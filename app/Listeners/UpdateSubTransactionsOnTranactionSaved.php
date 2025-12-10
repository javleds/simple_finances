<?php

namespace App\Listeners;

use App\Events\TransactionSaved;

class UpdateSubTransactionsOnTranactionSaved
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
        // TODO: Implement the update logic for sub-transactions here.
    }
}
