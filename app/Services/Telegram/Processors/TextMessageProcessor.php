<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;

class TextMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'text';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return !empty(data_get($telegramUpdate, 'message.text'));
    }

    public function process(array $telegramUpdate): string
    {
        $messageText = data_get($telegramUpdate, 'message.text');
        $userName = data_get($telegramUpdate, 'message.from.first_name', 'Usuario');

        return "¡Hola {$userName}! Has enviado el mensaje de texto: \"{$messageText}\"";
    }

    public function getPriority(): int
    {
        return 10;
    }
}
