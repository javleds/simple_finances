<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
            'scoped_balance' => 'float',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function updateBalance(): float
    {
        if (!$this->credit_card) {
            $this->balance = $this->transactions()->income()->sum('amount') - $this->transactions()->outcome()->sum('amount');
            $this->save();

            return $this->balance;
        }

        $this->balance = $this->credit_line
            - $this->transactions()->income()->sum('amount')
            - $this->transactions()->outcome()->sum('amount');

        $this->scoped_balance = $this->transactions()->beforeOf($this->next_cutoff_date)->income()->sum('amount')
            - $this->transactions()->beforeOf($this->next_cutoff_date)->outcome()->sum('amount');

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
}
