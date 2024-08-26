<?php

namespace App\Listeners;

use App\Events\AccountInviteCreated;
use App\Notifications\InviteAccountEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOnAccountInviteCreated
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
    public function handle(AccountInviteCreated $event): void
    {
        Notification::route('mail', $event->invite->email)
            ->notify(new InviteAccountEmail($event->invite));
    }
}
