<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Traits;

use FinSi\FinanceDomain\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToSharedUsers
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
