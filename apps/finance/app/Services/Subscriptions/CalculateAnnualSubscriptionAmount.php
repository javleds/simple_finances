<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use FinSi\FinanceDomain\Enums\Frequency;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Money\Decimal;

class CalculateAnnualSubscriptionAmount
{
    public function execute(Subscription $subscription): string
    {
        if (Frequency::Month === $subscription->frequency_type) {
            return Decimal::ratio($subscription->amount, 12, $subscription->frequency_unit);
        }

        if (Frequency::Year === $subscription->frequency_type) {
            return Decimal::divide($subscription->amount, $subscription->frequency_unit);
        }

        return Decimal::ratio($subscription->amount, 365, $subscription->frequency_unit);
    }
}
