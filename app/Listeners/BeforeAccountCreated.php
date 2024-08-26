<?php

namespace App\Listeners;

use App\Events\AccountCreationRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class BeforeAccountCreated
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
    public function handle(AccountCreationRequested $event): void
    {
        if (auth()->check()) {
            $event->account->user_id = auth()->id();
        }
    }
}
