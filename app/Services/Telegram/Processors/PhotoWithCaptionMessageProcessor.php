<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramFileService;

class PhotoWithCaptionMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService
    ) {}

    public static function getMessageType(): string
    {
        return 'photo_with_caption';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasPhoto($telegramUpdate)
            && TelegramMessageHelper::hasCaption($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $photos = data_get($telegramUpdate, 'message.photo', []);
        $caption = TelegramMessageHelper::getCaption($telegramUpdate);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        $photoCount = count($photos);

        $baseMessage = "¡Hola {$userName}! Has enviado una foto con {$photoCount} resoluciones y el texto: \"{$caption}\".";

        try {
            $fileInfo = $this->fileService->getFileFromPhoto($photos);

            if (!$fileInfo) {
                return "{$baseMessage} He recibido tanto tu imagen como tu mensaje.";
            }

            $fileSize = TelegramMessageHelper::formatFileSize($fileInfo['file_size']);

            if ($this->fileService->shouldAutoDownload($fileInfo)) {
                $downloadResult = $this->fileService->autoDownloadFile($fileInfo, 'photos');

                if ($downloadResult) {
                    TelegramMessageHelper::logFileProcessed('photo_with_caption', $fileInfo, $userName, $downloadResult);
                    return "{$baseMessage} Tamaño: {$fileSize}. La imagen ha sido guardada correctamente.";
                }
            }

            TelegramMessageHelper::logFileProcessed('photo_with_caption', $fileInfo, $userName);
            return "{$baseMessage} Tamaño: {$fileSize}. He recibido tanto tu imagen como tu mensaje.";

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('photo_with_caption', $e, $userName, ['photos' => $photos, 'caption' => $caption]);
            return "{$baseMessage} He recibido tanto tu imagen como tu mensaje.";
        }
    }

    public function getPriority(): int
    {
        return 25;
    }
}
