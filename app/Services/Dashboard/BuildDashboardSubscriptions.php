<?php

namespace App\Services\Dashboard;

use App\Enums\Frequency;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

class BuildDashboardSubscriptions
{
    public function execute(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $subscriptions = Subscription::query()
            ->whereNull('finished_at')
            ->get();
        $activeSubscriptions = $subscriptions
            ->filter(fn (Subscription $subscription): bool => $this->isActiveForSavingsTarget($subscription, $today));
        $targets = $activeSubscriptions
            ->map(fn (Subscription $subscription): array => $this->savingsTarget($subscription, $today));

        return [
            'annual_total' => $subscriptions->sum(fn (Subscription $subscription): float => $this->annualAmount($subscription)),
            'subscriptions_count' => $subscriptions->count(),
            'savings_target_today' => round($targets->sum('target_today'), 2),
            'upcoming_commitment' => round($targets->sum('amount'), 2),
            'nearest_payment' => $targets
                ->sortBy('next_payment_date')
                ->first(),
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

    private function isActiveForSavingsTarget(Subscription $subscription, CarbonImmutable $today): bool
    {
        if ($subscription->amount <= 0 || $subscription->frequency_unit <= 0) {
            return false;
        }

        return $this->nextPaymentDate($subscription, $today)->greaterThanOrEqualTo($today);
    }

    private function savingsTarget(Subscription $subscription, CarbonImmutable $today): array
    {
        $nextPaymentDate = $this->nextPaymentDate($subscription, $today);
        $cycleStartDate = $this->cycleStartDate($subscription, $nextPaymentDate);
        $targetToday = $subscription->amount * $this->cycleProgress($cycleStartDate, $nextPaymentDate, $today);

        return [
            'subscription_id' => $subscription->id,
            'name' => $subscription->name,
            'amount' => round((float) $subscription->amount, 2),
            'next_payment_date' => $nextPaymentDate->toDateString(),
            'cycle_start_date' => $cycleStartDate->toDateString(),
            'target_today' => round($targetToday, 2),
        ];
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
