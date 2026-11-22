<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends Model
{
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'account_id'                => 'integer',
            'user_id'                   => 'integer',
            'observed_balance'          => 'decimal:2',
            'previous_balance'          => 'decimal:2',
            'delta'                     => 'decimal:2',
            'observed_at'               => 'date',
            'adjustment_transaction_id' => 'integer',
        ];
    }
}
