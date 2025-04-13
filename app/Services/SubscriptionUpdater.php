<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionUpdater
{
    public function handle(): void
    {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->whereNull('next_payment_date')
            ->orWhere('next_payment_date', '<', Carbon::now());

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $previousPayment = $subscription->next_payment_date === null ? null : $subscription->next_payment_date->clone();
            $subscription->previous_payment_date = $previousPayment;
            $subscription->next_payment_date = $subscription->getNextPaymentDate();

            $subscription->saveQuietly();
        }
    }
}
