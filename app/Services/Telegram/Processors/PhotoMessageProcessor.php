<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;

class PhotoMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'photo';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        $hasPhoto = !empty(data_get($telegramUpdate, 'message.photo'));
        $hasCaption = !empty(data_get($telegramUpdate, 'message.caption'));

        return $hasPhoto && !$hasCaption;
    }

    public function process(array $telegramUpdate): string
    {
        $photos = data_get($telegramUpdate, 'message.photo', []);
        $userName = data_get($telegramUpdate, 'message.from.first_name', 'Usuario');
        $photoCount = count($photos);

        return "¡Hola {$userName}! Has enviado una foto con {$photoCount} resoluciones diferentes. He recibido tu imagen correctamente.";
    }

    public function getPriority(): int
    {
        return 15;
    }
}
