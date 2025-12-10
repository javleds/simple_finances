<?php

namespace App\Services\AccountInvites;

use App\Enums\InviteStatus;
use App\Models\Account;
use App\Models\AccountInvite;

readonly class Respond
{
    public function __construct(
        private NotifyOnInteract $notifyOnInteract,
        private EnableNotificationForInvitation $enableNotificationForInvitation,
    ) {}

    public function execute(AccountInvite $invite, InviteStatus $status): AccountInvite
    {
        $invite->update([
            'status' => $status,
        ]);

        Account::withoutGlobalScopes()
            ->find($invite->account_id)->users()
            ->attach(auth()->id(), [
                'percentage' => $invite->percentage,
            ]);

        $this->notifyOnInteract->execute($invite);
        $this->enableNotificationForInvitation->execute($invite);

        return $invite;
    }
}
