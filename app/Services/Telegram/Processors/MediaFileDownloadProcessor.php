<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramFileService;

class MediaFileDownloadProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService
    ) {}

    public static function getMessageType(): string
    {
        return 'media_download';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);
        $hasMedia = $this->fileService->getFileType($telegramUpdate) !== null;

        return $hasMedia && str_contains(strtolower($messageText), 'descargar');
    }

    public function process(array $telegramUpdate): string
    {
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        try {
            $fileData = $this->fileService->extractFileData($telegramUpdate);

            if (!$fileData) {
                return "¡Hola {$userName}! No pude extraer información del archivo multimedia.";
            }

            if (!TelegramMessageHelper::isFileSizeAllowedForDownload($fileData['file_size'])) {
                $fileSize = TelegramMessageHelper::formatFileSize($fileData['file_size']);
                return "¡Hola {$userName}! El archivo es muy grande ({$fileSize}) para descarga automática. Máximo permitido: 20MB.";
            }

            $downloadResult = $this->fileService->downloadAndStore($fileData['file_id'], 'telegram/downloads');
            $fileSize = TelegramMessageHelper::formatFileSize($fileData['file_size']);
            $fileType = $this->fileService->getFileType($telegramUpdate);

            TelegramMessageHelper::logFileProcessed('media_download', $fileData, $userName, $downloadResult);

            return "¡Hola {$userName}! Tu archivo {$fileType} ({$fileSize}) ha sido descargado y guardado exitosamente en: {$downloadResult['storage_path']}";

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('media_download', $e, $userName, ['update' => $telegramUpdate]);
            return "¡Hola {$userName}! Ocurrió un error al descargar tu archivo. Por favor intenta nuevamente.";
        }
    }

    public function getPriority(): int
    {
        return 50;
    }
}
