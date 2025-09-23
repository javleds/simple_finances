<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;

class VoiceMessageProcessor implements TelegramMessageProcessorInterface
{
    public static function getMessageType(): string
    {
        return 'voice';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasVoice($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $voiceData = data_get($telegramUpdate, 'message.voice');
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        $duration = data_get($voiceData, 'duration', 0);
        $durationFormatted = TelegramMessageHelper::formatDuration($duration);

        return "¡Hola {$userName}! Has enviado un mensaje de voz de {$durationFormatted}. En este momento no puedo procesar mensajes de voz, pero he recibido tu mensaje.";
    }

    public function getPriority(): int
    {
        return 20;
    }
}
