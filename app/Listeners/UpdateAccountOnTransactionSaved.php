<?php

namespace App\Listeners;

use App\Events\TransactionSaved;
use App\Models\Account;
use App\Services\Accounts\RecalculateAccountBalance;

class UpdateAccountOnTransactionSaved
{
    public function __construct(private readonly RecalculateAccountBalance $recalculateAccountBalance) {}

    /**
     * Handle the event.
     */
    public function handle(TransactionSaved $event): void
    {
        /** @var Account $account */
        $account = $event->transaction->account;
        $this->recalculateAccountBalance->execute($account);
    }
}
