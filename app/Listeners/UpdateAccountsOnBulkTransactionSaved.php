<?php

namespace App\Listeners;

use App\Events\BulkTransactionSaved;
use App\Models\Account;
use App\Services\Accounts\RecalculateAccountBalance;

class UpdateAccountsOnBulkTransactionSaved
{
    public function __construct(private readonly RecalculateAccountBalance $recalculateAccountBalance) {}

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
            $this->recalculateAccountBalance->execute($account);
        }
    }
}
