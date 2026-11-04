<?php

namespace App\Listeners;

use App\Events\AccountInviteCreated;
use App\Services\AccountInvites\SendInvitationNotification;

class NotifyOnAccountInviteCreated
{
    public function __construct(
        private readonly SendInvitationNotification $sendInvitationNotification,
    ) {}

    public function handle(AccountInviteCreated $event): void
    {
        $this->sendInvitationNotification->execute($event->invite);
    }
}
