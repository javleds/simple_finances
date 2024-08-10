<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;

class TermsAndConditions extends SimplePage implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.terms-and-conditions';

    public function getHeading(): string|Htmlable
    {
        return sprintf('Términos y Condiciones de "%s"', config('app.name'));
    }

    public function goToHomePage(): Action
    {
        return Action::make('go_home')
            ->color(Color::Teal)
            ->label('Ir al incio')
            ->url(Filament::getUrl());
    }
}
