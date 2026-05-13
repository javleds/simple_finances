<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\InviteAccountEmail;
use Illuminate\Support\Facades\Notification;

class SendInvitationNotification
{
    public function execute(AccountInvite $invite): void
    {
        $user = User::withoutGlobalScopes()->where('email', $invite->email)->first();

        if ($user && ! $user->canReceiveNotification(NotificationType::INVITATION_NOTIFICATION)) {
            return;
        }

        Notification::route('mail', $invite->email)
            ->notify(new InviteAccountEmail($invite));
    }
}
