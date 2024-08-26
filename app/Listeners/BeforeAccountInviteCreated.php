<?php

namespace App\Listeners;

use App\Events\AccountInviteCreatingRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
