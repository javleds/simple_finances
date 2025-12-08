<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingTransactionSum extends BaseWidget
{
    protected int | string | array $columnSpan = [
        'sm' => 1,
        'lg' => 1,
    ];

    protected ?string $heading = 'Pendientes';

    protected function getStats(): array
    {
        return [
            Stat::make('Por pagar', as_money(Transaction::where('status', 'pending')->sum('amount')))
                ->icon('heroicon-o-clock'),
        ];
    }
}
