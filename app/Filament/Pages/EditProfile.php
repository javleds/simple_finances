<?php

namespace App\Filament\Pages;

use App\Services\Telegram\TelegramVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EditProfile as AuthEditProfile;
use Filament\Support\Colors\Color;

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

                Group::make([
                    Placeholder::make('telegram_status')
                        ->label('Estado de Telegram')
                        ->content(fn () => $this->getTelegramStatusContent())
                        ->columnSpanFull(),
                ])
                    ->columnSpanFull()
                    ->columns(1),
            ]);
    }

    protected function getTelegramStatusContent(): string
    {
        $user = $this->getUser();

        if ($user->hasTelegramLinked()) {
            return '✅ Tu cuenta está vinculada con Telegram. Recibirás notificaciones automáticamente.';
        }

        return '⚠️ Tu cuenta no está vinculada con Telegram. Conecta tu cuenta para recibir notificaciones.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect_telegram')
                ->label('Conectar con Telegram')
                ->icon('heroicon-o-link')
                ->color(Color::Blue)
                ->visible(fn () => ! $this->getUser()->hasTelegramLinked())
                ->action('generateTelegramCode'),

            Action::make('disconnect_telegram')
                ->label('Desconectar Telegram')
                ->icon('heroicon-o-x-mark')
                ->color(Color::Red)
                ->visible(fn () => $this->getUser()->hasTelegramLinked())
                ->requiresConfirmation()
                ->modalHeading('Desconectar Telegram')
                ->modalDescription('¿Estás seguro de que deseas desconectar tu cuenta de Telegram? Ya no recibirás notificaciones.')
                ->action('disconnectTelegram'),
        ];
    }

    public function generateTelegramCode(): void
    {
        $user = $this->getUser();
        $verificationService = app(TelegramVerificationService::class);

        if (! $verificationService->canGenerateNewCode($user)) {
            Notification::make()
                ->title('Error al generar código')
                ->body('Ya tienes Telegram vinculado o has alcanzado el límite de códigos por hora.')
                ->danger()
                ->send();

            return;
        }

        try {
            $verificationCode = $verificationService->generateCode($user);

            Notification::make()
                ->title('Código generado')
                ->body("Tu código de verificación es: {$verificationCode->code}.\n\nEnvía al bot: `/verify {$verificationCode->code}`\n\nExpira en 10 minutos.")
                ->success()
                ->persistent()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Hubo un problema generando el código. Intenta nuevamente.')
                ->danger()
                ->send();
        }
    }

    public function disconnectTelegram(): void
    {
        $user = $this->getUser();
        $verificationService = app(TelegramVerificationService::class);

        try {
            $verificationService->unlinkUser($user);

            Notification::make()
                ->title('Desconectado exitosamente')
                ->body('Tu cuenta ha sido desconectada de Telegram.')
                ->success()
                ->send();

            $this->redirect($this->getUrl());

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Hubo un problema desconectando tu cuenta. Intenta nuevamente.')
                ->danger()
                ->send();
        }
    }
}
