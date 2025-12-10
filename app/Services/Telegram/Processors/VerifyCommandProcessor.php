<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramVerificationService;

class VerifyCommandProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramVerificationService $verificationService
    ) {}

    public static function getMessageType(): string
    {
        return 'verify_command';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);

        if (empty($messageText)) {
            return false;
        }

        // Verificar si es específicamente el comando /verify
        return preg_match('/^\/verify(\s+\d{6})?$/', trim($messageText));
    }

    public function process(array $telegramUpdate): string
    {
        $messageText = trim(TelegramMessageHelper::getText($telegramUpdate));
        $chatId = (string) data_get($telegramUpdate, 'message.chat.id');
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        // Verificar si el comando tiene el código
        if (preg_match('/^\/verify\s+(\d{6})$/', $messageText, $matches)) {
            $code = $matches[1];

            // Verificar código
            $user = $this->verificationService->verifyCode($code, $chatId);

            if (! $user) {
                return $this->getInvalidCodeMessage($userName, $code);
            }

            // Enviar mensaje de confirmación
            $this->verificationService->sendConfirmationMessage($user);

            return $this->getSuccessMessage($user->name);
        }

        // Si solo escribió /verify sin código
        return $this->getUsageMessage($userName);
    }

    public function getPriority(): int
    {
        return 35; // Mayor prioridad que el procesador de códigos normales
    }

    private function getUsageMessage(string $userName): string
    {
        return "¡Hola {$userName}! Para verificar tu código, usa:\n\n".
               "`/verify 123456`\n\n".
               "Donde `123456` es tu código de verificación de 6 dígitos.\n\n".
               '💡 También puedes enviar solo el código: `123456`';
    }

    private function getInvalidCodeMessage(string $userName, string $code): string
    {
        return "¡Hola {$userName}! El código `{$code}` no es válido o ha expirado.\n\n".
               "✅ Verifica que el código sea correcto\n".
               "🔄 Genera uno nuevo desde tu perfil si es necesario\n".
               '⏰ Los códigos expiran en 10 minutos';
    }

    private function getSuccessMessage(string $userName): string
    {
        return "🎉 ¡Perfecto {$userName}!\n\n".
               "✅ Tu cuenta ha sido vinculada exitosamente con Telegram\n".
               "🔔 Ahora recibirás notificaciones de tus finanzas directamente aquí\n".
               '💰 ¡Bienvenido a Simple Finances!';
    }
}
