<?php

namespace App\Services\AccountInvites;

use App\Enums\InviteStatus;
use App\Models\Account;
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

            Account::withoutGlobalScopes()
                ->find($invite->account_id)->users()
                ->attach(auth()->id());
            
            $this->notifyOnInteract->execute($invite);

            return $invite;
        }
}
