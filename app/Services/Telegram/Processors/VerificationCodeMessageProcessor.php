<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramVerificationService;

class VerificationCodeMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramVerificationService $verificationService
    ) {}

    public static function getMessageType(): string
    {
        return 'verification_code';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);
        
        if (empty($messageText)) {
            return false;
        }

        // Verificar si es un código de 6 dígitos
        return preg_match('/^\d{6}$/', trim($messageText));
    }

    public function process(array $telegramUpdate): string
    {
        $messageText = trim(TelegramMessageHelper::getText($telegramUpdate));
        $chatId = (string) data_get($telegramUpdate, 'message.chat.id');
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        // Verificar código
        $user = $this->verificationService->verifyCode($messageText, $chatId);

        if (!$user) {
            return $this->getInvalidCodeMessage($userName, $messageText);
        }

        // Enviar mensaje de confirmación
        $this->verificationService->sendConfirmationMessage($user);

        return $this->getSuccessMessage($user->name);
    }

    public function getPriority(): int
    {
        return 30; // Alta prioridad para códigos de verificación
    }

    private function getInvalidCodeMessage(string $userName, string $code): string
    {
        return "¡Hola {$userName}! El código {$code} no es válido o ha expirado. " .
               "Por favor verifica que el código sea correcto o genera uno nuevo desde tu perfil.";
    }

    private function getSuccessMessage(string $userName): string
    {
        return "¡Perfecto {$userName}! Tu cuenta ha sido vinculada exitosamente con Telegram. " .
               "Ahora recibirás notificaciones de tus finanzas directamente aquí. " .
               "¡Bienvenido a Simple Finances!";
    }
}