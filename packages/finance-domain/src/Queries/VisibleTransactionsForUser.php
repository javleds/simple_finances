<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Queries;

use FinSi\FinanceDomain\Models\Transaction;
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
