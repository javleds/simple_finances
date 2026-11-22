<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Enums\PaymentStatus;
use FinSi\FinanceDomain\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use BelongsToUser;
    use HasFactory;

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPaid(): bool
    {
        return PaymentStatus::Paid === $this->status;
    }

    public function isPending(): bool
    {
        return PaymentStatus::Pending === $this->status;
    }

    protected function casts(): array
    {
        return [
            'subscription_id' => 'integer',
            'amount'          => 'decimal:2',
            'status'          => PaymentStatus::class,
            'scheduled_at'    => 'date',
            'user_id'         => 'integer',
        ];
    }
}
