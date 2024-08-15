<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;
    use BelongsToUser;

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
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function feedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'feed_account_id', 'id');
    }

    public function seedAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'feed_account_id', 'id');
    }

    public function updateBalance(): float
    {
        if (!$this->credit_card) {
            $this->balance = $this->transactions()->income()->sum('amount') - $this->transactions()->outcome()->sum('amount');
            $this->save();

            return $this->balance;
        }

        $this->spent = $this->transactions()->income()->sum('amount')
            - $this->transactions()->outcome()->sum('amount');

        $this->available_credit = $this->credit_line - ($this->spent * -1);

        $this->balance = $this->transactions()->beforeOrEqualsTo($this->next_cutoff_date)->income()->sum('amount')
            - $this->transactions()->beforeOrEqualsTo($this->next_cutoff_date)->outcome()->sum('amount');

        $this->save();

        return $this->balance;
    }

    public function getBalanceUntilNow(): float
    {
        return
            $this->transactions()->untilNow()->income()->sum('amount')
            - $this->transactions()->untilNow()->outcome()->sum('amount')
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
