<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramFileService;

class AudioMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService
    ) {}

    public static function getMessageType(): string
    {
        return 'audio';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasAudio($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $audioData = data_get($telegramUpdate, 'message.audio');
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        $duration = data_get($audioData, 'duration', 0);
        $title = data_get($audioData, 'title', 'Archivo de audio');
        $durationFormatted = TelegramMessageHelper::formatDuration($duration);

        $baseMessage = "¡Hola {$userName}! Has enviado un archivo de audio \"{$title}\" de {$durationFormatted}.";

        try {
            $fileInfo = $this->fileService->getFileFromAudio($audioData);

            if (!$fileInfo) {
                return "{$baseMessage} He recibido tu archivo de audio correctamente.";
            }

            $fileSize = TelegramMessageHelper::formatFileSize($fileInfo['file_size']);

            if ($this->fileService->shouldAutoDownload($fileInfo)) {
                $downloadResult = $this->fileService->autoDownloadFile($fileInfo, 'audio');

                if ($downloadResult) {
                    TelegramMessageHelper::logFileProcessed('audio', $fileInfo, $userName, $downloadResult);
                    return "{$baseMessage} Tamaño: {$fileSize}. El archivo de audio ha sido guardado correctamente.";
                }
            }

            TelegramMessageHelper::logFileProcessed('audio', $fileInfo, $userName);
            return "{$baseMessage} Tamaño: {$fileSize}. He recibido tu archivo de audio correctamente.";

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('audio', $e, $userName, ['audio_data' => $audioData]);
            return "{$baseMessage} He recibido tu archivo de audio correctamente.";
        }
    }

    public function getPriority(): int
    {
        return 20;
    }
}
