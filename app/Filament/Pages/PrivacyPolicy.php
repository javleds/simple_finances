<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;

class PrivacyPolicy extends SimplePage implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.privacy-policy';

    public function getHeading(): string|Htmlable
    {
        return sprintf('Política de Privacidad de la aplicación "%s"', config('app.name'));
    }

    public function goToHomePage(): Action
    {
        return Action::make('go_home')
            ->color(Color::Teal)
            ->label('Ir al incio')
            ->url(Filament::getUrl());
    }
}
