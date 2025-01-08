<?php

namespace App\Providers;

use App\Overrides\InjectableSupportPageComponents;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportPageComponents\SupportPageComponents;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
//        $this->app->bind(SupportPageComponents::class, InjectableSupportPageComponents::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $defaultCurrency = 'MXN';
        $defaultLocale = config('app.locale');

        Model::unguard();
        Table::$defaultCurrency = $defaultCurrency;
        Table::$defaultNumberLocale = $defaultLocale;
        Infolist::$defaultNumberLocale = $defaultCurrency;
        Infolist::$defaultNumberLocale = $defaultLocale;

        Action::configureUsing(function (Action $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        TableAction::configureUsing(function (TableAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        Page::$formActionsAlignment = Alignment::Right;
    }
}
