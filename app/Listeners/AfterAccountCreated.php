<?php

namespace App\Listeners;

use App\Events\AccountCreated;

class AfterAccountCreated
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
    public function handle(AccountCreated $event): void
    {
        if (auth()->check()) {
            $event->account->users()->attach(auth()->id());
        }
    }
}
