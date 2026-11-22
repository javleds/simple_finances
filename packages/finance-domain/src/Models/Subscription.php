<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use Carbon\Carbon;
use FinSi\FinanceDomain\Enums\Frequency;
use FinSi\FinanceDomain\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function sprintf;

class Subscription extends Model
{
    use BelongsToUser;
    use HasFactory;

    public function isMonthly(): bool
    {
        return Frequency::Month === $this->frequency_type;
    }

    public function isYearly(): bool
    {
        return Frequency::Year === $this->frequency_type;
    }

    public function isDaily(): bool
    {
        return Frequency::Day === $this->frequency_type;
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
            if (null === $nextDate) {
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
        return null !== $this->finished_at;
    }

    public function getAddFrequency(): string
    {
        return sprintf('+ %s %s', $this->frequency_unit, $this->frequency_type->value);
    }

    protected function casts(): array
    {
        return [
            'user_id'               => 'integer',
            'finished_at'           => 'date',
            'started_at'            => 'date',
            'amount'                => 'decimal:2',
            'feed_account_id'       => 'integer',
            'frequency_type'        => Frequency::class,
            'frequency_unit'        => 'integer',
            'next_payment_date'     => 'date',
            'previous_payment_date' => 'date',
        ];
    }
}
