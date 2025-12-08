<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AccountResource;
use App\Filament\Resources\SubscriptionResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Widgets\AccountBalancePlot;
use App\Filament\Widgets\PendingTransactionSum;
use App\Filament\Widgets\PendingTransactionsByAccount;
use App\Filament\Widgets\SubscriptionMonthlyProjection;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;

class SimpleFinancesDashboard extends Dashboard
{
    protected static string $routePath = '/dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    public function getWidgets(): array
    {
        return [
            AccountBalancePlot::class,
            PendingTransactionSum::class,
            PendingTransactionsByAccount::class,
            SubscriptionMonthlyProjection::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'sm' => 1,
            'lg' => 1,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('go_to_subscriptions')
                ->label('Ir a subscricpiones')
                ->icon('heroicon-o-calendar-date-range')
                ->url(fn () => SubscriptionResource::getUrl())
                ->outlined(),
            Action::make('go_to_accounts')
                ->label('Ir a cuentas')
                ->icon('heroicon-o-credit-card')
                ->url(fn () => AccountResource::getUrl())
                ->outlined(),
            Action::make('go_to_transactions')
                ->label('Ir a transacciones')
                ->icon('heroicon-o-banknotes')
                ->url(fn () => TransactionResource::getUrl())
                ->outlined(),
        ];
    }
}
