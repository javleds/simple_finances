<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\TelegramFileService;

class PhotoMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService
    ) {}

    public static function getMessageType(): string
    {
        return 'photo';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasPhoto($telegramUpdate)
            && !TelegramMessageHelper::hasCaption($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $photos = data_get($telegramUpdate, 'message.photo', []);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        $photoCount = count($photos);

        $baseMessage = "¡Hola {$userName}! Has enviado una foto con {$photoCount} resoluciones diferentes.";

        try {
            $fileInfo = $this->fileService->getFileFromPhoto($photos);

            if (!$fileInfo) {
                return "{$baseMessage} He recibido tu imagen correctamente.";
            }

            TelegramMessageHelper::logFileProcessed('photo', $fileInfo, $userName);

            $fileSize = TelegramMessageHelper::formatFileSize($fileInfo['file_size']);
            return "{$baseMessage} Tamaño del archivo: {$fileSize}. He recibido tu imagen correctamente.";

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('photo', $e, $userName, ['photos' => $photos]);
            return "{$baseMessage} He recibido tu imagen correctamente.";
        }
    }

    public function getPriority(): int
    {
        return 15;
    }
}
