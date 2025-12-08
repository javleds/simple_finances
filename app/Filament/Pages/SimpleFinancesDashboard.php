<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PendingTransactionSum;
use App\Filament\Widgets\PendingTransactionsByAccount;
use App\Filament\Widgets\SubscriptionMonthlyProjection;
use Filament\Pages\Dashboard;

class SimpleFinancesDashboard extends Dashboard
{
    protected static string $routePath = '/dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    public function getWidgets(): array
    {
        return [
            SubscriptionMonthlyProjection::class,
            PendingTransactionSum::class,
            PendingTransactionsByAccount::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'sm' => 1,
            // 'lg' => 2,
        ];
    }
}
