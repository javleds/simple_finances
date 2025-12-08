<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingTransactionSum extends BaseWidget
{
    protected int | string | array $columnSpan = [
        'sm' => 1,
        'lg' => 1,
    ];

    protected ?string $heading = 'Cuentas';

    protected function getStats(): array
    {
        return [
            Stat::make('Por pagar', as_money(Transaction::where('status', 'pending')->sum('amount')))
                ->icon('heroicon-o-clock'),
            Stat::make('Cuentas activas', Account::whereNull('deleted_at')->count())
                ->icon('heroicon-o-building-library'),
            Stat::make('Cuentas compartidas', Account::has('users', '>', 1)->whereNull('deleted_at')->count())
                ->icon('heroicon-o-share'),
        ];
    }
}
