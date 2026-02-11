<?php

namespace App\Models;

use App\Enums\SharedTransactionNotificationAction;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedTransactionNotificationItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'batch_id' => 'integer',
            'transaction_id' => 'integer',
            'modifier_id' => 'integer',
            'action' => SharedTransactionNotificationAction::class,
            'amount' => 'float',
            'scheduled_at' => 'immutable_datetime',
            'type' => TransactionType::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SharedTransactionNotificationBatch::class, 'batch_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
