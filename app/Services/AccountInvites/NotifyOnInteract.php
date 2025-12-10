<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\InviteAccountInteractionEmail;
use Illuminate\Support\Facades\Notification;

class NotifyOnInteract
{
    public function execute(AccountInvite $invite): void
    {
        $user = User::withoutGlobalScopes()->find($invite->user_id);

        if (! $user->canReceiveNotification(NotificationType::INVITATION_INTERACTION)) {
            return;
        }

        if (! $user->notificableAccounts()->get()->contains($invite->account)) {
            return;
        }

        Notification::send(
            $user,
            new InviteAccountInteractionEmail($invite)
        );
    }
}
