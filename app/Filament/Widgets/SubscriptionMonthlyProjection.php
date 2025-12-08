<?php

namespace App\Filament\Widgets;

use App\Enums\Frequency;
use App\Models\Account;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionMonthlyProjection extends BaseWidget
{
    protected function getStats(): array
    {
        $subscriptions = Subscription::whereNull('finished_at')->get();

        $amount = 0.0;
        foreach ($subscriptions as $subscription) {
            if ($subscription->frequency_type === Frequency::Month) {
                $amount += $subscription->amount;

                continue;
            }

            if ($subscription->frequency_type === Frequency::Year) {
                $amount += ($subscription->amount / 12);

                continue;
            }

            $amount += round(($subscription->pricing / ($subscription->periodicity_every * 100 / 30.4)), 2);
        }

        return [
            Stat::make('Ahorro mensual recomendado', as_money($amount)),
            Stat::make('Ahorro quincenal recomendado', as_money($amount / 2)),
        ];
    }
}
