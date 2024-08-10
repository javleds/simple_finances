<?php

namespace App\Filament\Pages;

use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;

class PrivacyPolicy extends SimplePage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.privacy-policy';

    public function getHeading(): string|Htmlable
    {
        return sprintf('Política de Privacidad de la aplicación "%s"', config('app.name'));
    }
}
