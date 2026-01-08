<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\Frequency;
use App\Traits\BelongsToUser;

class FixedIncome extends Model
{
    /** @use HasFactory<\Database\Factories\FixedIncomeFactory> */
    use HasFactory;
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'frequency' => Frequency::class,
        ];
    }

    public function partials(): HasMany
    {
        return $this->hasMany(PartialFixedIncome::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(FixedOutcome::class);
    }
}
