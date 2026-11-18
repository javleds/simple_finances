<?php

declare(strict_types=1);

namespace App\Services\AccountInvites;

use App\Notifications\InviteAccountApiEmail;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\Notification;

class SendApiInvitationNotification
{
    public function execute(AccountInvite $invite): void
    {
        $user = User::withoutGlobalScopes()->where('email', $invite->email)->first();

        if ($user instanceof User && $user->isVirtualCustodian()) {
            return;
        }

        $authPath = $user instanceof User ? 'login' : 'register';

        Notification::route('mail', $invite->email)
            ->notify(new InviteAccountApiEmail($invite, $authPath));
    }
}
