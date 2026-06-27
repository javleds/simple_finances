<?php

namespace App\Services\Dashboard;

use App\Models\Subscription;
use App\Services\Subscriptions\BuildSubscriptionSavingsTarget;
use App\Services\Subscriptions\CalculateAnnualSubscriptionAmount;
use Carbon\CarbonImmutable;

class BuildDashboardSubscriptions
{
    public function __construct(
        private readonly CalculateAnnualSubscriptionAmount $calculateAnnualSubscriptionAmount,
        private readonly BuildSubscriptionSavingsTarget $buildSubscriptionSavingsTarget,
    ) {}

    public function execute(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $subscriptions = Subscription::query()
            ->whereNull('finished_at')
            ->get();
        $targets = $subscriptions
            ->map(fn (Subscription $subscription) => $this->buildSubscriptionSavingsTarget->execute($subscription, $today))
            ->filter()
            ->map(fn ($target): array => $target->toArray());

        return [
            'annual_total' => $subscriptions->sum(
                fn (Subscription $subscription): float => $this->calculateAnnualSubscriptionAmount->execute($subscription)
            ),
            'subscriptions_count' => $subscriptions->count(),
            'savings_target_today' => round($targets->sum('target_today'), 2),
            'upcoming_commitment' => round($targets->sum('amount'), 2),
            'nearest_payment' => $targets
                ->sortBy('next_payment_date')
                ->first(),
        ];
    }
}
