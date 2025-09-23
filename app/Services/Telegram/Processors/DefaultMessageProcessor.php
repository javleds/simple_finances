<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;

class DefaultMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'default';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return !empty(data_get($telegramUpdate, 'message'));
    }

    public function process(array $telegramUpdate): string
    {
        $userName = data_get($telegramUpdate, 'message.from.first_name', 'Usuario');

        return "¡Hola {$userName}! He recibido tu mensaje, pero no tengo un procesador específico para este tipo de contenido. Por favor intenta con un mensaje de texto, foto, video o audio.";
    }

    public function getPriority(): int
    {
        return 1;
    }
}
