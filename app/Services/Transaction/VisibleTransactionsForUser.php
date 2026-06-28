<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class VisibleTransactionsForUser
{
    public function query(int $userId): Builder
    {
        return Transaction::withoutGlobalScopes()
            ->whereHas('account', function (Builder $query) use ($userId): void {
                $query
                    ->whereNull('deleted_at')
                    ->whereHas('users', fn (Builder $query) => $query->where('users.id', $userId));
            });
    }
}
