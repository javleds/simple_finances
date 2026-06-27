<?php

namespace App\Services\Subscriptions;

use App\Enums\Frequency;
use App\Models\Subscription;

class CalculateAnnualSubscriptionAmount
{
    public function execute(Subscription $subscription): float
    {
        if ($subscription->frequency_type === Frequency::Month) {
            return ($subscription->amount / $subscription->frequency_unit) * 12;
        }

        if ($subscription->frequency_type === Frequency::Year) {
            return $subscription->amount / $subscription->frequency_unit;
        }

        return ($subscription->amount / $subscription->frequency_unit) * 365;
    }
}
