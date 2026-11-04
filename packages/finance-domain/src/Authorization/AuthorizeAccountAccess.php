<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use function abort_unless;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;

class AuthorizeAccountAccess
{
    public function isOwner(Account $account, int $userId): bool
    {
        return $account->user_id === $userId;
    }

    public function isMember(Account $account, int $userId): bool
    {
        return $account->users()->withoutGlobalScopes()->where('users.id', $userId)->exists();
    }

    public function ensureOwner(Account $account, int $userId): void
    {
        abort_unless($this->isOwner($account, $userId), 403);
    }

    public function ensureMember(Account $account, int $userId): void
    {
        abort_unless(
            $this->isMember($account, $userId),
            403,
        );
    }

    public function ensureBelongsToAccount(Account $account, int $accountId): void
    {
        abort_unless($accountId === $account->id, 404);
    }

    public function ensureAccountUser(Account $account, User $user): void
    {
        abort_unless($account->users()->withoutGlobalScopes()->where('users.id', $user->id)->exists(), 404);
    }
}
