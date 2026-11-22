<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Enums\SharedTransactionNotificationAction;
use FinSi\FinanceDomain\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedTransactionNotificationItem extends Model
{
    use HasFactory;

    protected $hidden = ['amount_legacy', 'amount_exact'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SharedTransactionNotificationBatch::class, 'batch_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    protected function casts(): array
    {
        return [
            'batch_id'       => 'integer',
            'account_id'     => 'integer',
            'transaction_id' => 'integer',
            'modifier_id'    => 'integer',
            'action'         => SharedTransactionNotificationAction::class,
            'amount'         => 'decimal:2',
            'scheduled_at'   => 'immutable_datetime',
            'type'           => TransactionType::class,
        ];
    }
}
