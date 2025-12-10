<?php

namespace App\Listeners;

use App\Events\BulkTransactionSaved;
use App\Models\Account;

class UpdateAccountsOnBulkTransactionSaved
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
    public function handle(BulkTransactionSaved $event): void
    {
        $transactions = $event->transactions;

        $accountIds = collect();

        foreach ($transactions as $transaction) {
            if ($accountIds->contains($transaction->account_id)) {
                continue;
            }

            $accountIds->add($transaction->account_id);

            /** @var Account $account */
            $account = $transaction->account;
            $account->updateBalance();
        }
    }
}
