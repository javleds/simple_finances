<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class VisibleTransactionsForUser
{
    public function query(int $userId): Builder
    {
        return Transaction::withoutGlobalScopes()
            ->whereHas('account.users', fn (Builder $query) => $query->where('users.id', $userId));
    }
}
