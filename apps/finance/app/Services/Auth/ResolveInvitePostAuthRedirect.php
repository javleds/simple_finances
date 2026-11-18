<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Support\SpaUrl;
use FinSi\FinanceDomain\Enums\InviteStatus;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;

readonly class ResolveInvitePostAuthRedirect
{
    public const ACCOUNT_INVITES_ACTION = 'account-invites';

    public function __construct(
        private SpaUrl $spaUrl,
    ) {
    }

    public function execute(User $user, ?string $action): ?array
    {
        if ($user->isVirtualCustodian() || self::ACCOUNT_INVITES_ACTION !== $action) {
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
            'url'    => $this->spaUrl->to('admin/invitations'),
        ];
    }
}
