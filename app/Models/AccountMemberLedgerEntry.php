<?php

namespace App\Models;

use App\Enums\AccountMemberLedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMemberLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_id',
        'transaction_id',
        'related_user_id',
        'type',
        'amount',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'transaction_id' => 'integer',
            'related_user_id' => 'integer',
            'type' => AccountMemberLedgerEntryType::class,
            'amount' => 'float',
            'occurred_at' => 'immutable_datetime',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }
}
