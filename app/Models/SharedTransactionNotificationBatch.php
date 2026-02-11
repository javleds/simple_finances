<?php

namespace App\Models;

use App\Enums\SharedTransactionNotificationBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedTransactionNotificationBatch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'account_id' => 'integer',
            'status' => SharedTransactionNotificationBatchStatus::class,
            'window_started_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SharedTransactionNotificationItem::class, 'batch_id');
    }
}
