<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class OwnedTransactionsForUser
{
    public function query(int $userId): Builder
    {
        return Transaction::withoutGlobalScopes()
            ->where('user_id', $userId);
    }
}
