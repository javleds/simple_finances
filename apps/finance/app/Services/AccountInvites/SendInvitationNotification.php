<?php

declare(strict_types=1);

namespace App\Services\AccountInvites;

use App\Notifications\InviteAccountEmail;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\Notification;

class SendInvitationNotification
{
    public function execute(AccountInvite $invite): void
    {
        if (User::query()->where('email', $invite->email)->whereNotNull('virtual_account_id')->exists()) {
            return;
        }

        Notification::route('mail', $invite->email)
            ->notify(new InviteAccountEmail($invite));
    }
}
