<?php

namespace App\Filament\Pages;

use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;

class TermsAndConditions extends SimplePage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.terms-and-conditions';

    public function getHeading(): string|Htmlable
    {
        return sprintf('Términos y Condiciones de "%s"', config('app.name'));
    }
}
