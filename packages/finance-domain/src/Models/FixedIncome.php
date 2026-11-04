<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Enums\FixedIncomeFrequency;
use FinSi\FinanceDomain\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedIncome extends Model
{
    use BelongsToUser;
    use HasFactory;

    public function partials(): HasMany
    {
        return $this->hasMany(PartialFixedIncome::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(FixedOutcome::class);
    }

    protected function casts(): array
    {
        return [
            'user_id'   => 'integer',
            'frequency' => FixedIncomeFrequency::class,
        ];
    }
}
