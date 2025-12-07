<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Pages\Page;

class SimpleFinancesDashboard extends Dashboard
{
    protected static string $routePath = '/dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.simple-finances-dashboard';
}
