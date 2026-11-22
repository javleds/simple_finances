<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Services\Subscriptions\BuildSubscriptionSavingsTarget;
use App\Services\Subscriptions\CalculateAnnualSubscriptionAmount;
use Carbon\CarbonImmutable;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Queries\UserOwnedResources;
use Illuminate\Contracts\Auth\Guard;

class BuildDashboardSubscriptions
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly Guard $auth,
        private readonly CalculateAnnualSubscriptionAmount $calculateAnnualSubscriptionAmount,
        private readonly BuildSubscriptionSavingsTarget $buildSubscriptionSavingsTarget,
    ) {
    }

    public function execute(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $subscriptions = $this->userOwnedResources->constrain(Subscription::query(), (int) $this->auth->id())
            ->whereNull('finished_at')
            ->get();
        $targets = $subscriptions
            ->map(fn (Subscription $subscription) => $this->buildSubscriptionSavingsTarget->execute($subscription, $today))
            ->filter()
            ->map(fn ($target): array => $target->toArray());

        return [
            'annual_total'         => Decimal::sum($subscriptions->map(
                fn (Subscription $subscription): string => $this->calculateAnnualSubscriptionAmount->execute($subscription)
            )),
            'subscriptions_count'  => $subscriptions->count(),
            'savings_target_today' => Decimal::sum($targets, 'target_today'),
            'upcoming_commitment'  => Decimal::sum($targets, 'amount'),
            'nearest_payment'      => $targets
                ->sortBy('next_payment_date')
                ->first(),
        ];
    }
}
