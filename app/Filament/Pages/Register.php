<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Auth\Register as BaseRegisterPage;

class Register extends BaseRegisterPage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.register';

    public function goToTermsAndConditions(): Action
    {
        return Action::make('terms_and_conditions')
            ->link()
            ->label('Términos y condiciones')
            ->url(route('terms_and_conditions'), true);
    }

    public function goToPrivacyPolicy(): Action
    {
        return Action::make('privacy_policy')
            ->link()
            ->label('Política de privacidad')
            ->url(route('privacy_policy'), true);
    }
}
