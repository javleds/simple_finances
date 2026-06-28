<?php

namespace App\Services\Accounts;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;

class VisibleAccountsForUser
{
    public function query(int $userId): Builder
    {
        return $this->queryIncludingDeleted($userId)
            ->whereNull('deleted_at');
    }

    public function queryIncludingDeleted(int $userId): Builder
    {
        return Account::withoutGlobalScopes()
            ->whereHas('users', fn (Builder $query) => $query->where('users.id', $userId));
    }
}
