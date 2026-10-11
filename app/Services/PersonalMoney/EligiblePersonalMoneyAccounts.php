<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;

class EligiblePersonalMoneyAccounts
{
    public function execute(int $userId): Builder
    {
        return Account::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->where('virtual', false)
            ->where('credit_card', false)
            ->whereHas('users', static fn (Builder $query): Builder => $query->where('users.id', $userId))
            ->has('users', '=', 1);
    }
}
