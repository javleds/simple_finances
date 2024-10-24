<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Events\SubscriptionSaved;
use App\Events\SubscriptionSaving;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected $dispatchesEvents = [
        'saving' => SubscriptionSaving::class,
        'saved' => SubscriptionSaved::class,
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'finished_at' => 'date',
            'started_at' => 'date',
            'amount' => 'float',
            'feed_account_id' => 'integer',
            'frequency_type' => Frequency::class,
            'frequency_unit' => 'integer',
            'next_payment_date' => 'date',
            'previous_payment_date' => 'date',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function feedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function getNextPaymentDate(): Carbon
    {
        $startedAt = $this->started_at->clone();
        $limit = Carbon::now();

        if ($startedAt >= $limit) {
            return $startedAt;
        }

        $frequency = $this->getAddFrequency();
        $nextDate = null;

        do {
            if ($nextDate === null) {
                $nextDate = $startedAt->clone()->modify($frequency);
            }

            $nextDate = $nextDate->clone()->modify($frequency);
        } while ($nextDate < $limit);

        return $nextDate;
    }

    public function getPreviousPaymentDate(): Carbon
    {
        $nextDate = $this->next_payment_date ?? $this->getNextPaymentDate();

        if ($nextDate == $this->started_at) {
            return $nextDate;
        }

        return $nextDate->clone()->modify(
            $this->getAddFrequency()
        );
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function getAddFrequency(): string
    {
        return sprintf('+ %s %s', $this->frequency_unit, $this->frequency_type->value);
    }
}
