<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

readonly class DailyUpdater
{
    public function __construct(private UpdateNextPayment $updateNextPayment)
    {
    }

    public function handle(): void
    {
        $subscriptions = Subscription::withoutGlobalScopes()->get();

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $this->updateNextPayment->handle($subscription);
        }
    }
}
