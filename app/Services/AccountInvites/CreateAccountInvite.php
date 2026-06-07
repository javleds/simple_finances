<?php

namespace App\Services\AccountInvites;

use App\Dto\AccountInviteDto;
use App\Models\AccountInvite;
use Illuminate\Support\Facades\DB;

readonly class CreateAccountInvite
{
    public function __construct(
        private SendApiInvitationNotification $sendApiInvitationNotification,
    ) {}

    public function execute(AccountInviteDto $dto): AccountInvite
    {
        $invite = $dto->toInvite();

        $this->sendApiInvitationNotification->execute($invite);

        return DB::transaction(function () use ($invite): AccountInvite {
            $invite->saveQuietly();

            return $invite;
        });
    }
}
