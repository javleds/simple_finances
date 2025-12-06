<?php

namespace App\Models;

use App\Events\AccountCreated;
use App\Events\AccountCreationRequested;
use App\Models\Scopes\BelongsToSharedUsersScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToSharedUsersScope::class])]
class Account extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'balance' => 'float',
            'credit_card' => 'bool',
            'credit_line' => 'float',
            'cutoff_day' => 'int',
            'next_cutoff_date' => 'datetime',
            'available_credit' => 'float',
            'spent' => 'float',
            'feed_account_id' => 'int',
            'virtual' => 'bool',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['percentage']);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function feedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'feed_account_id', 'id');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(AccountInvite::class);
    }

    public function seedAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'feed_account_id', 'id');
    }

    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    public function updateBalance(): float
    {
        if (!$this->credit_card) {
            $this->balance = $this->transactions()->completed()->income()->sum('amount')
                - $this->transactions()->completed()->outcome()->sum('amount');
            $this->save();

            return $this->balance;
        }

        $this->spent = $this->transactions()->completed()->income()->sum('amount')
            - $this->transactions()->completed()->outcome()->sum('amount');

        $this->available_credit = $this->credit_line - ($this->spent * -1);

        $this->balance = $this->transactions()->beforeOrEqualsTo($this->next_cutoff_date)->completed()->income()->sum('amount')
            - $this->transactions()->beforeOrEqualsTo($this->next_cutoff_date)->completed()->outcome()->sum('amount');

        $this->save();

        return $this->balance;
    }

    public function getBalanceUntilNow(): float
    {
        return
            $this->transactions()->untilNow()->completed()->income()->sum('amount')
            - $this->transactions()->untilNow()->completed()->outcome()->sum('amount')
            ?? 0.0;
    }

    public function isCreditCard(): bool
    {
        return $this->credit_card;
    }

    public function getTransferBalanceLabelAttribute(): string
    {
        $balance = $this->credit_card ? $this->available_credit : $this->balance;

        return sprintf(
            '%s [%s$ %s]',
            $this->name,
            $balance >= 0 ? '' : '-',
            number_format(abs($balance), 2)
        );
    }
}
