<?php

namespace App\Services\AccountInvites;

use App\Enums\InviteStatus;
use App\Models\AccountInvite;

readonly class Respond
{
    public function __construct(private NotifyOnInteract $notifyOnInteract)
    {
    }

    public function execute(AccountInvite $invite, InviteStatus $status): AccountInvite
        {
            $invite->update([
                'status' => $status,
            ]);

            $invite->account->users()->attach(auth()->user()->id);

            $this->notifyOnInteract->execute($invite);

            return $invite;
        }
}
