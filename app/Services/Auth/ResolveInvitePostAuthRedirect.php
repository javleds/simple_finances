<?php

namespace App\Services\Auth;

use App\Enums\InviteStatus;
use App\Models\AccountInvite;
use App\Models\User;
use App\Support\SpaUrl;

readonly class ResolveInvitePostAuthRedirect
{
    public const ACCOUNT_INVITES_ACTION = 'account-invites';

    public function __construct(
        private SpaUrl $spaUrl,
    ) {}

    public function execute(User $user, ?string $action): ?array
    {
        if ($action !== self::ACCOUNT_INVITES_ACTION) {
            return null;
        }

        $hasPendingInvite = AccountInvite::query()
            ->where('email', $user->email)
            ->where('status', InviteStatus::Pending)
            ->exists();

        if (! $hasPendingInvite) {
            return null;
        }

        return [
            'action' => self::ACCOUNT_INVITES_ACTION,
            'url' => $this->spaUrl->to('admin/invitations'),
        ];
    }
}
