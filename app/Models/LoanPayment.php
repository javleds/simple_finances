<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    use HasFactory;
    use BelongsToUser;

    public function casts(): array
    {
        return [
            'amount' => 'float',
            'scheduled_at' => 'date',
            'paid_at' => 'date',
            'user_id' => 'integer',
            'loan_id' => 'integer',
            'transaction_id' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
