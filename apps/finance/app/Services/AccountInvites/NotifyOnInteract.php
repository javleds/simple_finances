<?php

namespace App\Services\AccountInvites;

use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\NotificationType;
use FinSi\FinanceDomain\Models\User;
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
            new InviteAccountInteractionEmail($invite, request()->is('api/*'))
        );
    }
}
