<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Dto\SubscriptionSavingsTargetDto;
use Carbon\CarbonImmutable;
use FinSi\FinanceDomain\Enums\Frequency;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Money\Decimal;

use function max;
use function min;

class BuildSubscriptionSavingsTarget
{
    public function execute(Subscription $subscription, CarbonImmutable $today): ?SubscriptionSavingsTargetDto
    {
        if (! $this->isActiveForSavingsTarget($subscription, $today)) {
            return null;
        }

        $nextPaymentDate = $this->nextPaymentDate($subscription, $today);
        $cycleStartDate = $this->cycleStartDate($subscription, $nextPaymentDate);
        $targetToday = $this->targetAmount($subscription->amount, $cycleStartDate, $nextPaymentDate, $today);

        return new SubscriptionSavingsTargetDto(
            subscriptionId: $subscription->id,
            name: $subscription->name,
            amount: Decimal::normalize($subscription->amount),
            nextPaymentDate: $nextPaymentDate->toDateString(),
            cycleStartDate: $cycleStartDate->toDateString(),
            targetToday: $targetToday,
        );
    }

    private function isActiveForSavingsTarget(Subscription $subscription, CarbonImmutable $today): bool
    {
        if (Decimal::compare($subscription->amount, '0.00') <= 0 || $subscription->frequency_unit <= 0) {
            return false;
        }

        return $this->nextPaymentDate($subscription, $today)->greaterThanOrEqualTo($today);
    }

    private function targetAmount(
        string $amount,
        CarbonImmutable $cycleStartDate,
        CarbonImmutable $nextPaymentDate,
        CarbonImmutable $today,
    ): string {
        if ($today->lessThanOrEqualTo($cycleStartDate)) {
            return '0.00';
        }

        if ($today->greaterThanOrEqualTo($nextPaymentDate)) {
            return Decimal::normalize($amount);
        }

        $totalDays = max(1, (int) $cycleStartDate->diffInDays($nextPaymentDate));
        $elapsedDays = (int) $cycleStartDate->diffInDays($today);

        return Decimal::ratio($amount, min($totalDays, max(0, $elapsedDays)), $totalDays);
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
            Frequency::Day   => $nextPaymentDate->subDays($subscription->frequency_unit),
            Frequency::Month => $nextPaymentDate->subMonthsNoOverflow($subscription->frequency_unit),
            Frequency::Year  => $nextPaymentDate->subYearsNoOverflow($subscription->frequency_unit),
        };
    }

    private function addFrequency(CarbonImmutable $date, Subscription $subscription): CarbonImmutable
    {
        return match ($subscription->frequency_type) {
            Frequency::Day   => $date->addDays($subscription->frequency_unit),
            Frequency::Month => $date->addMonthsNoOverflow($subscription->frequency_unit),
            Frequency::Year  => $date->addYearsNoOverflow($subscription->frequency_unit),
        };
    }
}
