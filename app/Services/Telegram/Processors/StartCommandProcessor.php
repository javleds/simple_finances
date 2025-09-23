<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;

class StartCommandProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'start_command';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);

        if (empty($messageText)) {
            return false;
        }

        return str_starts_with(trim($messageText), '/start');
    }

    public function process(array $telegramUpdate): string
    {
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        return $this->getWelcomeMessage($userName);
    }

    public function getPriority(): int
    {
        return 25;
    }

    private function getWelcomeMessage(string $userName): string
    {
        return "¡Hola {$userName}! Bienvenido al bot de Simple Finances. " .
               "Este bot te permitirá recibir notificaciones importantes sobre tus finanzas.\n\n" .
               "Para vincular tu cuenta:\n" .
               "1. Ve a tu perfil en la aplicación web\n" .
               "2. Haz clic en 'Conectar con Telegram'\n" .
               "3. Envía aquí el código de 6 dígitos que aparecerá\n\n" .
               "¡Es así de fácil! Una vez vinculada tu cuenta, recibirás notificaciones automáticas " .
               "sobre transacciones, recordatorios de objetivos y más.";
    }
}
