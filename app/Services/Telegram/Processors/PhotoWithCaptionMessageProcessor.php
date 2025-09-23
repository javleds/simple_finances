<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;

class PhotoWithCaptionMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'photo_with_caption';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasPhoto($telegramUpdate)
            && TelegramMessageHelper::hasCaption($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $photos = data_get($telegramUpdate, 'message.photo', []);
        $caption = TelegramMessageHelper::getCaption($telegramUpdate);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        $photoCount = count($photos);

        return "¡Hola {$userName}! Has enviado una foto con {$photoCount} resoluciones y el texto: \"{$caption}\". He recibido tanto tu imagen como tu mensaje.";
    }

    public function getPriority(): int
    {
        return 25;
    }
}
