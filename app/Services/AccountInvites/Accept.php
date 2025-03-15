<?php

namespace App\Services\AccountInvites;

use App\Models\AccountInvite;

readonly class Accept
{
    public function __construct(private NotifyOnInteract $notifyOnInteract)
    {
    }

    public function execute(AccountInvite $invite): AccountInvite
        {
            $invite->update([
                'status' => 'accepted',
            ]);

            $invite->account->users()->attach(auth()->user()->id);

            $this->notifyOnInteract->execute($invite);

            return $invite;
        }
}
