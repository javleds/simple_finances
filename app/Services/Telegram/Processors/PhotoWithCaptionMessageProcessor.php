<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;

class PhotoWithCaptionMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'photo_with_caption';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        $hasPhoto = !empty(data_get($telegramUpdate, 'message.photo'));
        $hasCaption = !empty(data_get($telegramUpdate, 'message.caption'));

        return $hasPhoto && $hasCaption;
    }

    public function process(array $telegramUpdate): string
    {
        $photos = data_get($telegramUpdate, 'message.photo', []);
        $caption = data_get($telegramUpdate, 'message.caption');
        $userName = data_get($telegramUpdate, 'message.from.first_name', 'Usuario');
        $photoCount = count($photos);

        return "¡Hola {$userName}! Has enviado una foto con {$photoCount} resoluciones y el texto: \"{$caption}\". He recibido tanto tu imagen como tu mensaje.";
    }

    public function getPriority(): int
    {
        return 25;
    }
}
