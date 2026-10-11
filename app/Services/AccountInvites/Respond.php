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
        \Illuminate\Support\Facades\DB::transaction(function () use ($invite, $status): void {
            $invite->update(['status' => $status]);

            if ($status !== InviteStatus::Accepted) {
                return;
            }

            $account = Account::withoutGlobalScopes()->lockForUpdate()->findOrFail($invite->account_id);
            app(\App\Services\Categories\ConvertAccountCategoryCatalog::class)->execute($account);
            $account->users()->syncWithoutDetaching([
                auth()->id() => ['percentage' => $invite->percentage],
            ]);
        });

        $this->notifyOnInteract->execute($invite);
        if ($status === InviteStatus::Accepted) {
            $this->enableNotificationForInvitation->execute($invite);
        }

        return $invite;
    }
}
