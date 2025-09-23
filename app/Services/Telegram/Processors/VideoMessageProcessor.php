<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;

class VideoMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'video';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return !empty(data_get($telegramUpdate, 'message.video'));
    }

    public function process(array $telegramUpdate): string
    {
        $videoData = data_get($telegramUpdate, 'message.video');
        $userName = data_get($telegramUpdate, 'message.from.first_name', 'Usuario');
        $duration = data_get($videoData, 'duration', 0);
        $width = data_get($videoData, 'width', 0);
        $height = data_get($videoData, 'height', 0);

        return "¡Hola {$userName}! Has enviado un video de {$duration} segundos con resolución {$width}x{$height}. He recibido tu video correctamente.";
    }

    public function getPriority(): int
    {
        return 20;
    }
}
