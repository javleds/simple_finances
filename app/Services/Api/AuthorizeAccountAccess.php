<?php

namespace App\Services\Api;

use App\Models\Account;
use App\Models\User;

class AuthorizeAccountAccess
{
    public function ensureOwner(Account $account, ?int $userId = null): void
    {
        abort_unless($account->user_id === $this->resolveUserId($userId), 403);
    }

    public function ensureMember(Account $account, ?int $userId = null): void
    {
        abort_unless(
            $account->users()->withoutGlobalScopes()->where('users.id', $this->resolveUserId($userId))->exists(),
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

    private function resolveUserId(?int $userId): int
    {
        return $userId ?? auth()->id();
    }
}
