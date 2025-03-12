<?php

namespace App\Listeners;

use App\Events\AccountInviteInteracted;
use App\Models\Account;
use App\Models\NotificationType;
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
        $user = User::withoutGlobalScopes()->find($event->invite->user_id);

        if (!$user->canReceiveNotification(NotificationType::INVITATION_NOTIFICATION)) {
            return;
        }

        Notification::send(
            $user,
            new InviteAccountInteractionEmail($event->invite)
        );
    }
}
