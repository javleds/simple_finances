<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as AuthEditProfile;
use Filament\Pages\Page;

class EditProfile extends AuthEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                TextInput::make('phone_number')
                    ->label('Número de teléfono')
                    ->helperText('Proveer tu numero de teléfono te permite ligar tu cuenta a Telegram para mayores beneficios.')
                    ->tel()
                    ->placeholder('+52 1234657890'),
            ]);
    }
}
