<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;
use App\Models\User;

class EnableNotificationForInvitation
{
    public function execute(AccountInvite $invite): void
    {
        if (! $invite->isAccepted()) {
            return;
        }

        $user = User::withoutGlobalScopes()->where('email', $invite->email)->first();

        if (! $user) {
            return;
        }

        $user->notificableAccounts()->attach($invite->account_id);
    }
}
