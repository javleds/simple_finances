<?php

declare(strict_types=1);

namespace App\Services\AccountInvites;

use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;

class EnableNotificationForInvitation
{
    public function execute(AccountInvite $invite): void
    {
        if (! $invite->isAccepted()) {
            return;
        }

        $user = User::withoutGlobalScopes()->where('email', $invite->email)->first();

        if (! $user || $user->isVirtualCustodian()) {
            return;
        }

        $user->notificableAccounts()->attach($invite->account_id);
    }
}
