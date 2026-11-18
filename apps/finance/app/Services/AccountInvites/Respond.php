<?php

declare(strict_types=1);

namespace App\Services\AccountInvites;

use function abort_if;
use function app;
use function auth;

use FinSi\FinanceDomain\Enums\InviteStatus;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;

readonly class Respond
{
    public function __construct(
        private NotifyOnInteract $notifyOnInteract,
        private EnableNotificationForInvitation $enableNotificationForInvitation,
    ) {
    }

    public function execute(AccountInvite $invite, InviteStatus $status): AccountInvite
    {
        abort_if(auth()->user()?->isVirtualCustodian(), 403);

        \Illuminate\Support\Facades\DB::transaction(function () use ($invite, $status): void {
            $invite->update(['status' => $status]);

            if (InviteStatus::Accepted !== $status) {
                return;
            }

            $account = Account::withoutGlobalScopes()->lockForUpdate()->findOrFail($invite->account_id);
            app(\App\Services\Categories\ConvertAccountCategoryCatalog::class)->execute($account);
            $account->users()->syncWithoutDetaching([
                auth()->id() => ['percentage' => $invite->percentage],
            ]);
        });

        $this->notifyOnInteract->execute($invite);
        if (InviteStatus::Accepted === $status) {
            $this->enableNotificationForInvitation->execute($invite);
        }

        return $invite;
    }
}
