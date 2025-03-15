<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;

readonly class Decline
{
    public function __construct(private NotifyOnInteract $notifyOnInteract)
    {
    }

    public function execute(AccountInvite $invite): AccountInvite
    {
        $invite->update([
            'status' => 'declined',
        ]);

        $this->notifyOnInteract->execute($invite);

        return $invite;
    }
}
