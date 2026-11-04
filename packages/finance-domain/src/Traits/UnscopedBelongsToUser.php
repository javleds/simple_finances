<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Traits;

use FinSi\FinanceDomain\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait UnscopedBelongsToUser
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
