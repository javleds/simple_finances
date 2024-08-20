<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Events\SubscriptionSaving;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected $dispatchesEvents = [
        'saving' => SubscriptionSaving::class,
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

        do {
            $nextDate = $startedAt->clone()->modify($this->add_frequency);
        } while ($nextDate < $limit);

        return $nextDate;
    }

    public function getPreviousPaymentDate(): Carbon
    {
        $nextDate = $this->next_payment_date ?? $this->getNextPaymentDate();

        if ($nextDate == $this->started_at) {
            return $nextDate;
        }

        return $nextDate->clone()->modify($this->sub_frequency);
    }
}
