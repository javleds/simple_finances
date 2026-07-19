<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'percentage',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'transaction_id' => 'integer',
            'user_id' => 'integer',
            'percentage' => 'float',
            'amount' => 'float',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
