<?php

namespace App\Listeners;

use App\Events\AccountInviteCreatingRequested;

class BeforeAccountInviteCreated
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
    public function handle(AccountInviteCreatingRequested $event): void
    {
        if (auth()->check()) {
            $event->invite->user_id = auth()->id();
        }
    }
}
