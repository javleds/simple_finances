<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'observed_balance' => 'float',
            'previous_balance' => 'float',
            'delta' => 'float',
            'observed_at' => 'date',
            'adjustment_transaction_id' => 'integer',
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

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }
}
