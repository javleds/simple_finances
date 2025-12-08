<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionMonthlyProjection extends BaseWidget
{
    protected function getStats(): array
    {
        $subscriptions = Subscription::whereNull('finished_at')->get();

        $monthlyAmount = $subscriptions->sum(
            fn (Subscription $subscription): float => $this->getMonthlyProjectionAmount($subscription)
        );
        $yearlyAmount = $subscriptions->sum(
            fn (Subscription $subscription): float => $this->getYearlyProjectionAmount($subscription)
        );

        return [
            Stat::make('Gasto anual en suscripciones', as_money($yearlyAmount)),
            Stat::make('Gasto mensual en suscripciones', as_money($monthlyAmount)),
            Stat::make('Ahorro quincenal en suscripciones', as_money($monthlyAmount / 2)),
        ];
    }

    private function getMonthlyProjectionAmount(Subscription $subscription): float
    {
        if ($subscription->isMonthly()) {
            return $subscription->amount;
        }

        if ($subscription->isYearly()) {
            return $subscription->amount / 12;
        }

        if ($subscription->isDaily()) {
            return ($subscription->amount / $subscription->frequency_unit) * 30.4;
        }

        return $subscription->amount;
    }

    private function getYearlyProjectionAmount(Subscription $subscription): float
    {
        if ($subscription->isMonthly()) {
            return $subscription->amount * 12;
        }

        if ($subscription->isYearly()) {
            return $subscription->amount;
        }

        if ($subscription->isDaily()) {
            return ($subscription->amount / $subscription->frequency_unit) * 365;
        }

        return $subscription->amount;
    }
}
