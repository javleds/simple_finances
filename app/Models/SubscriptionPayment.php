<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'subscription_id' => 'integer',
            'amount' => 'float',
            'status' => PaymentStatus::class,
            'scheduled_at' => 'date',
            'user_id' => 'integer',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
