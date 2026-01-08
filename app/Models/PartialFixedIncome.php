<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToUser;

class PartialFixedIncome extends Model
{
    /** @use HasFactory<\Database\Factories\PartialFixedIncomeFactory> */
    use HasFactory;
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'fixed_income_id' => 'integer',
            'user_id' => 'integer',
            'amount' => 'float',
            'user_id' => 'integer',
        ];
    }

    public function fixedIncome(): BelongsTo
    {
        return $this->belongsTo(FixedIncome::class);
    }
}
