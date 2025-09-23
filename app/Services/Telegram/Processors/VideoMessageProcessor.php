<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramFileService;

class VideoMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService
    ) {}

    public static function getMessageType(): string
    {
        return 'video';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasVideo($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $videoData = data_get($telegramUpdate, 'message.video');
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        $duration = data_get($videoData, 'duration', 0);
        $width = data_get($videoData, 'width', 0);
        $height = data_get($videoData, 'height', 0);

        $durationFormatted = TelegramMessageHelper::formatDuration($duration);
        $baseMessage = "¡Hola {$userName}! Has enviado un video de {$durationFormatted} con resolución {$width}x{$height}.";

        try {
            $fileInfo = $this->fileService->getFileFromVideo($videoData);

            if (!$fileInfo) {
                return "{$baseMessage} He recibido tu video correctamente.";
            }

            $fileSize = TelegramMessageHelper::formatFileSize($fileInfo['file_size']);

            if (!TelegramMessageHelper::isFileSizeAllowedForDownload($fileInfo['file_size'])) {
                return "{$baseMessage} Tamaño: {$fileSize}. El archivo es muy grande para descarga automática.";
            }

            $downloadResult = $this->fileService->downloadAndStore($fileInfo['file_id'], 'telegram/videos');

            TelegramMessageHelper::logFileProcessed('video', $fileInfo, $userName, $downloadResult);

            return "{$baseMessage} Tamaño: {$fileSize}. El video ha sido descargado y guardado correctamente.";

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('video', $e, $userName, ['video_data' => $videoData]);
            return "{$baseMessage} He recibido tu video correctamente.";
        }
    }

    public function getPriority(): int
    {
        return 20;
    }
}
