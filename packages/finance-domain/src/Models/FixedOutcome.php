<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Enums\FixedOutcomeType;
use FinSi\FinanceDomain\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedOutcome extends Model
{
    use BelongsToUser;
    use HasFactory;

    public function casts(): array
    {
        return [
            'fixed_income_id' => 'integer',
            'user_id'         => 'integer',
            'amount'          => 'decimal:2',
            'type'            => FixedOutcomeType::class,
        ];
    }

    public function fixedIncome(): BelongsTo
    {
        return $this->belongsTo(FixedIncome::class);
    }
}
