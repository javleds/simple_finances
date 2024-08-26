<?php

namespace App\Listeners;

use App\Events\AccountInviteInteracted;
use App\Models\Account;
use App\Models\User;
use App\Notifications\InviteAccountInteractionEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class NotifyAccountInviteInteraction
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
    public function handle(AccountInviteInteracted $event): void
    {
        Notification::send(User::withoutGlobalScopes()->find($event->invite->user_id), new InviteAccountInteractionEmail($event->invite));
    }
}
