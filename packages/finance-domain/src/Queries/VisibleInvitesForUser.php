<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Queries;

use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VisibleInvitesForUser
{
    public function query(User $actor): Builder
    {
        return AccountInvite::query()->where('email', $actor->email);
    }
}
