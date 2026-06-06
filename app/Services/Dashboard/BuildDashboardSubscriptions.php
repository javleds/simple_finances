<?php

namespace App\Services\Dashboard;

use App\Enums\Frequency;
use App\Models\Subscription;

class BuildDashboardSubscriptions
{
    public function execute(): array
    {
        $subscriptions = Subscription::query()
            ->whereNull('finished_at')
            ->get();

        return [
            'annual_total' => $subscriptions->sum(fn (Subscription $subscription): float => $this->annualAmount($subscription)),
            'subscriptions_count' => $subscriptions->count(),
        ];
    }

    private function annualAmount(Subscription $subscription): float
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
