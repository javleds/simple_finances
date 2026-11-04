<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Queries;

use Illuminate\Database\Eloquent\Builder;

class UserOwnedResources
{
    public function constrain(Builder $query, int $userId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('user_id'), $userId);
    }
}
