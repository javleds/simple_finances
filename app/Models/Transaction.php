<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Traits\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'account_id' => 'integer',
            'type' => TransactionType::class,
            'amount' => 'float',
            'scheduled_at' => 'immutable_datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeIncome(Builder $builder): void
    {
        $builder->where('type', TransactionType::Income);
    }

    public function scopeOutcome(Builder $builder): void
    {
        $builder->where('type', TransactionType::Outcome);
    }

    public function scopeUntilNow(Builder $builder): void
    {
        $builder->whereDate('scheduled_at', '<=', CarbonImmutable::now());
    }
}
