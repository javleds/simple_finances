<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramFileService;

class DocumentMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService
    ) {}

    public static function getMessageType(): string
    {
        return 'document';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasDocument($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $documentData = data_get($telegramUpdate, 'message.document');
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        $fileName = data_get($documentData, 'file_name', 'Documento');
        $mimeType = data_get($documentData, 'mime_type', 'desconocido');

        $baseMessage = "¡Hola {$userName}! Has enviado un documento \"{$fileName}\" (tipo: {$mimeType}).";

        try {
            $fileInfo = $this->fileService->getFileFromDocument($documentData);

            if (! $fileInfo) {
                return "{$baseMessage} He recibido tu documento correctamente.";
            }

            $fileSize = TelegramMessageHelper::formatFileSize($fileInfo['file_size']);

            if ($this->fileService->shouldAutoDownload($fileInfo)) {
                $downloadResult = $this->fileService->autoDownloadFile($fileInfo, 'documents');

                if ($downloadResult) {
                    TelegramMessageHelper::logFileProcessed('document', $fileInfo, $userName, $downloadResult);

                    return "{$baseMessage} Tamaño: {$fileSize}. El documento ha sido guardado correctamente.";
                }
            }

            TelegramMessageHelper::logFileProcessed('document', $fileInfo, $userName);

            return "{$baseMessage} Tamaño: {$fileSize}. He recibido tu documento correctamente.";

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('document', $e, $userName, ['document_data' => $documentData]);

            return "{$baseMessage} He recibido tu documento correctamente.";
        }
    }

    public function getPriority(): int
    {
        return 20;
    }
}
