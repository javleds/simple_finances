<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Queries;

use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class OwnedTransactionsForUser
{
    public function query(int $userId): Builder
    {
        return Transaction::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereHas('account', fn (Builder $query) => $query->whereNull('deleted_at'));
    }
}
