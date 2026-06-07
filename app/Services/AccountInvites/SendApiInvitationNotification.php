<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;
use App\Models\User;
use App\Notifications\InviteAccountApiEmail;
use Illuminate\Support\Facades\Notification;

class SendApiInvitationNotification
{
    public function execute(AccountInvite $invite): void
    {
        $user = User::withoutGlobalScopes()->where('email', $invite->email)->first();

        if (!$user) {
            return;
        }

        Notification::route('mail', $invite->email)
            ->notify(new InviteAccountApiEmail($invite));
    }
}
