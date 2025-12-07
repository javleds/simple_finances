<?php

namespace App\Models;

use App\Enums\TransactionStatus;
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
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToUserThroughAccount::class])]
class Transaction extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => TransactionStatus::Completed->value,
    ];

    protected function casts(): array
    {
        return [
            'parent_transaction_id' => 'integer',
            'user_id' => 'integer',
            'account_id' => 'integer',
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount' => 'float',
            'scheduled_at' => 'immutable_datetime',
            'financial_goal_id' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialGoal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class);
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    public function subTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'parent_transaction_id');
    }

    public function isSubTransaction(): bool
    {
        return $this->parent_transaction_id !== null;
    }

    public function scopeIncome(Builder $builder): void
    {
        $builder->where('type', TransactionType::Income);
    }

    public function scopeOutcome(Builder $builder): void
    {
        $builder->where('type', TransactionType::Outcome);
    }

    public function scopeCompleted(Builder $builder): void
    {
        $builder->where('status', TransactionStatus::Completed);
    }

    public function scopePending(Builder $builder): void
    {
        $builder->where('status', TransactionStatus::Pending);
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
            if (auth()->check() && empty($model->user_id)) {
                $model->user_id = auth()->id();
            }
        });
    }
}
