<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;

class TextMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'text';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasText($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        return "¡Hola {$userName}! Has enviado el mensaje de texto: \"{$messageText}\"";
    }

    public function getPriority(): int
    {
        return 10;
    }
}
