<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AccountMember extends Pivot
{
    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'percentage' => 'decimal:2',
        ];
    }
}
