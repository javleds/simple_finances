<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingTransactionSum extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total pendiente', as_money(Transaction::where('status', 'pending')->sum('amount'))),
        ];
    }
}
