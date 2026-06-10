<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;
use App\Notifications\InviteAccountEmail;
use Illuminate\Support\Facades\Notification;

class SendInvitationNotification
{
    public function execute(AccountInvite $invite): void
    {
        Notification::route('mail', $invite->email)
            ->notify(new InviteAccountEmail($invite));
    }
}
