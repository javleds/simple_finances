<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Telegram\TelegramFileService;
use App\Services\Transaction\TransactionProcessorService;

class PhotoMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService,
        private readonly TransactionProcessorService $transactionProcessor
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

        // Verificar autenticación
        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);

        if (!$user) {
            return "Hola {$userName}! Para poder procesar imágenes y crear transacciones, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        auth()->login($user);

        try {
            $fileInfo = $this->fileService->getFileFromPhoto($photos);

            if (!$fileInfo) {
                return "No pude procesar la imagen. Por favor, inténtalo de nuevo.";
            }

            // Descargar imagen temporalmente
            $downloadResult = $this->fileService->downloadFileTemporarily($fileInfo);

            if (!$downloadResult) {
                return "No pude descargar la imagen para procesarla. Inténtalo de nuevo.";
            }

            // Procesar imagen directamente con el TransactionProcessor
            // Este servicio ya incluye detección de acciones internamente
            $result = $this->transactionProcessor->processImage($downloadResult['full_path'], '', $user);

            // Limpiar archivo temporal
            $this->cleanupTemporaryFile($downloadResult['full_path']);

            return $result;

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('photo', $e, $userName, ['photos' => $photos]);
            return "Ocurrió un error al procesar la imagen. Por favor, inténtalo de nuevo.";
        }
    }

    public function getPriority(): int
    {
        return 15;
    }

    private function cleanupTemporaryFile(string $filePath): void
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to cleanup temporary file', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }
}
