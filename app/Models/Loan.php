<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'done_at' => 'date',
            'last_payment_date' => 'date',
            'next_payment_date' => 'date',
            'completed_at' => 'date',
            'amount' => 'float',
            'feed_account_id' => 'integer',
            'number_of_payments' => 'integer',
            'payments_done' => 'integer',
            'paid' => 'float',
            'to_pay' => 'float',
        ];
    }

    public function feedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
