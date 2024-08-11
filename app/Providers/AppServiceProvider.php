<?php

namespace App\Providers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\CreateAction as TableCreateAction;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Filament\Tables\Actions\EditAction as TableEditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        CreateAction::configureUsing(function (CreateAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        TableCreateAction::configureUsing(function(TableCreateAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        EditAction::configureUsing(function(EditAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        TableEditAction::configureUsing(function(TableEditAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        DeleteAction::configureUsing(function (DeleteAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        TableDeleteAction::configureUsing(function (TableDeleteAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        Page::$formActionsAlignment = Alignment::Right;
    }
}
