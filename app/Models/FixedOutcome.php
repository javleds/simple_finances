<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToUser;

class FixedOutcome extends Model
{
    /** @use HasFactory<\Database\Factories\FixedOutcomeFactory> */
    use HasFactory;
    use BelongsToUser;

    public function casts(): array
    {
        return [
            'fixed_income_id' => 'integer',
            'user_id' => 'integer',
            'amount' => 'float',
        ];
    }

    public function fixedIncome(): BelongsTo
    {
        return $this->belongsTo(FixedIncome::class);
    }
}
