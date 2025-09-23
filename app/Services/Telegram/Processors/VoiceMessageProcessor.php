<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramFileService;

class VoiceMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService
    ) {}

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

        $baseMessage = "¡Hola {$userName}! Has enviado un mensaje de voz de {$durationFormatted}.";

        try {
            $fileInfo = $this->fileService->getFileFromVoice($voiceData);

            if (!$fileInfo) {
                return "{$baseMessage} He recibido tu mensaje de voz correctamente.";
            }

            $fileSize = TelegramMessageHelper::formatFileSize($fileInfo['file_size']);

            if ($this->fileService->shouldAutoDownload($fileInfo)) {
                $downloadResult = $this->fileService->autoDownloadFile($fileInfo, 'voice');

                if ($downloadResult) {
                    TelegramMessageHelper::logFileProcessed('voice', $fileInfo, $userName, $downloadResult);
                    return "{$baseMessage} Tamaño: {$fileSize}. El mensaje de voz ha sido guardado correctamente.";
                }
            }

            TelegramMessageHelper::logFileProcessed('voice', $fileInfo, $userName);
            return "{$baseMessage} Tamaño: {$fileSize}. He recibido tu mensaje de voz correctamente.";

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('voice', $e, $userName, ['voice_data' => $voiceData]);
            return "{$baseMessage} He recibido tu mensaje de voz correctamente.";
        }
    }

    public function getPriority(): int
    {
        return 20;
    }
}
