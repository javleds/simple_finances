<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Scopes\BelongsToUserScope;
use App\Models\Scopes\BelongsToUserThroughAccount;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToUserThroughAccount::class])]
class Transaction extends Model
{
    use HasFactory;

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

    public function scopeBeforeOf(Builder $builder, ?Carbon $date): void
    {
        if (!$date) {
            return;
       }

        $builder->whereDate('scheduled_at', '<', $date->toDateString());
    }

    public function scopeBeforeOrEqualsTo(Builder $builder, ?Carbon $date): void
    {
        if (!$date) {
            return;
        }

        $builder->whereDate('scheduled_at', '<=', $date->toDateString());
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }
}
