<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\InviteAccountApiEmail;
use Illuminate\Support\Facades\Notification;

class SendApiInvitationNotification
{
    public function execute(AccountInvite $invite): void
    {
        $user = User::withoutGlobalScopes()->where('email', $invite->email)->first();

        if ($user && ! $user->canReceiveNotification(NotificationType::INVITATION_NOTIFICATION)) {
            return;
        }

        $authPath = $user instanceof User ? 'login' : 'register';

        Notification::route('mail', $invite->email)
            ->notify(new InviteAccountApiEmail($invite, $authPath));
    }
}
