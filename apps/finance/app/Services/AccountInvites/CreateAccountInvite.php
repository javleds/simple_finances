<?php

declare(strict_types=1);

namespace App\Services\AccountInvites;

use App\Dto\AccountInviteDto;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class CreateAccountInvite
{
    public function __construct(
        private SendApiInvitationNotification $sendApiInvitationNotification,
    ) {
    }

    public function execute(AccountInviteDto $dto): AccountInvite
    {
        $invite = $dto->toInvite();

        if (User::query()->where('email', $invite->email)->whereNotNull('virtual_account_id')->exists()) {
            throw ValidationException::withMessages(['email' => 'Los custodios virtuales no pueden recibir invitaciones.']);
        }

        $this->sendApiInvitationNotification->execute($invite);

        return DB::transaction(function () use ($invite): AccountInvite {
            $invite->saveQuietly();

            return $invite;
        });
    }
}
