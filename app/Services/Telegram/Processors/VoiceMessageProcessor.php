<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;

class VoiceMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'voice';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return !empty(data_get($telegramUpdate, 'message.voice'));
    }

    public function process(array $telegramUpdate): string
    {
        $voiceData = data_get($telegramUpdate, 'message.voice');
        $userName = data_get($telegramUpdate, 'message.from.first_name', 'Usuario');
        $duration = data_get($voiceData, 'duration', 0);

        return "¡Hola {$userName}! Has enviado un mensaje de voz de {$duration} segundos. En este momento no puedo procesar mensajes de voz, pero he recibido tu mensaje.";
    }

    public function getPriority(): int
    {
        return 20;
    }
}
