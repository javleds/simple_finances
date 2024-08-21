<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionUpdater
{
    public function handle(): void
    {
        $subscriptions = Subscription::withoutGlobalScopes()->where('next_cutoff_date', '<', Carbon::now());

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $subscription->previous_payment_date = $subscription->next_payment_date->clone();
            $subscription->next_payment_date = $subscription->getNextPaymentDate();

            $subscription->save();
        }
    }
}
