<?php

namespace App\Listeners;

use App\Events\AccountInviteCreated;
use App\Models\NotificationType;
use App\Models\User;
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
        $user = User::withoutGlobalScopes()->where('email', $event->invite->email)->first();

        if ($user && !$user->canReceiveNotification(NotificationType::INVITATION_NOTIFICATION)) {
            return;
        }

        Notification::route('mail', $event->invite->email)
            ->notify(new InviteAccountEmail($event->invite));
    }
}
