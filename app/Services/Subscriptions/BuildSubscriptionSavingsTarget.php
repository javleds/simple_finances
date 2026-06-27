<?php

namespace App\Services\Subscriptions;

use App\Dto\SubscriptionSavingsTargetDto;
use App\Enums\Frequency;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

class BuildSubscriptionSavingsTarget
{
    public function execute(Subscription $subscription, CarbonImmutable $today): ?SubscriptionSavingsTargetDto
    {
        if (! $this->isActiveForSavingsTarget($subscription, $today)) {
            return null;
        }

        $nextPaymentDate = $this->nextPaymentDate($subscription, $today);
        $cycleStartDate = $this->cycleStartDate($subscription, $nextPaymentDate);
        $targetToday = $subscription->amount * $this->cycleProgress($cycleStartDate, $nextPaymentDate, $today);

        return new SubscriptionSavingsTargetDto(
            subscriptionId: $subscription->id,
            name: $subscription->name,
            amount: round((float) $subscription->amount, 2),
            nextPaymentDate: $nextPaymentDate->toDateString(),
            cycleStartDate: $cycleStartDate->toDateString(),
            targetToday: round($targetToday, 2),
        );
    }

    private function isActiveForSavingsTarget(Subscription $subscription, CarbonImmutable $today): bool
    {
        if ($subscription->amount <= 0 || $subscription->frequency_unit <= 0) {
            return false;
        }

        return $this->nextPaymentDate($subscription, $today)->greaterThanOrEqualTo($today);
    }

    private function cycleProgress(
        CarbonImmutable $cycleStartDate,
        CarbonImmutable $nextPaymentDate,
        CarbonImmutable $today,
    ): float {
        if ($today->lessThanOrEqualTo($cycleStartDate)) {
            return 0.0;
        }

        if ($today->greaterThanOrEqualTo($nextPaymentDate)) {
            return 1.0;
        }

        $totalDays = max(1, $cycleStartDate->diffInDays($nextPaymentDate));
        $elapsedDays = $cycleStartDate->diffInDays($today);

        return min(1.0, max(0.0, $elapsedDays / $totalDays));
    }

    private function nextPaymentDate(Subscription $subscription, CarbonImmutable $today): CarbonImmutable
    {
        $nextPaymentDate = $subscription->next_payment_date
            ? CarbonImmutable::instance($subscription->next_payment_date)->startOfDay()
            : CarbonImmutable::instance($subscription->started_at)->startOfDay();

        while ($nextPaymentDate->lessThan($today)) {
            $nextPaymentDate = $this->addFrequency($nextPaymentDate, $subscription);
        }

        return $nextPaymentDate;
    }

    private function cycleStartDate(Subscription $subscription, CarbonImmutable $nextPaymentDate): CarbonImmutable
    {
        return match ($subscription->frequency_type) {
            Frequency::Day => $nextPaymentDate->subDays($subscription->frequency_unit),
            Frequency::Month => $nextPaymentDate->subMonthsNoOverflow($subscription->frequency_unit),
            Frequency::Year => $nextPaymentDate->subYearsNoOverflow($subscription->frequency_unit),
        };
    }

    private function addFrequency(CarbonImmutable $date, Subscription $subscription): CarbonImmutable
    {
        return match ($subscription->frequency_type) {
            Frequency::Day => $date->addDays($subscription->frequency_unit),
            Frequency::Month => $date->addMonthsNoOverflow($subscription->frequency_unit),
            Frequency::Year => $date->addYearsNoOverflow($subscription->frequency_unit),
        };
    }
}
