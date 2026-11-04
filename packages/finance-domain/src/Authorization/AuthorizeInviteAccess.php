<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use function abort_unless;

use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;

class AuthorizeInviteAccess
{
    public function ensureVisible(AccountInvite $invite, User $actor): void
    {
        abort_unless($invite->user_id === $actor->id || $invite->email === $actor->email, 403);
    }
}
