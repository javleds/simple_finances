<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use FinSi\FinanceDomain\Models\Account;

class AccountParticipants
{
    public function ids(Account $account): array
    {
        return $account->users()->withoutGlobalScopes()->pluck('users.id')
            ->map(static fn ($id): int => (int) $id)->all();
    }
}
