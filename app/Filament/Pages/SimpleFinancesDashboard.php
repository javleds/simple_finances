<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PendingTransactionsByAccount;
use App\Filament\Widgets\SubscriptionMonthlyProjection;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;

class SimpleFinancesDashboard extends Dashboard
{
    protected static string $routePath = '/dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public function getWidgets(): array
    {
        return [
            SubscriptionMonthlyProjection::class,
            PendingTransactionsByAccount::class,
        ];
    }
}
