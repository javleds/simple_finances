<?php

namespace App\Listeners;

use App\Events\AccountSaved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
