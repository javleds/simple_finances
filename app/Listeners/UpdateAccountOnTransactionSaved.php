<?php

namespace App\Listeners;

use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
        $account->balance = Transaction::income()->sum('amount') - Transaction::outcome()->sum('amount');
        $account->save();
    }
}
