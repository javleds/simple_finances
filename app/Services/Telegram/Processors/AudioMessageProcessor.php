<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\MessageActionDetectionServiceInterface;
use App\Contracts\TelegramMessageProcessorInterface;
use App\Enums\MessageAction;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Telegram\MessageActionProcessorFactory;
use App\Services\Telegram\TelegramFileService;
use App\Services\Transaction\TransactionProcessorService;
use Illuminate\Support\Facades\Log;

class AudioMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService,
        private readonly TransactionProcessorService $transactionProcessor,
        private readonly MessageActionDetectionServiceInterface $actionDetectionService,
        private readonly MessageActionProcessorFactory $actionProcessorFactory
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

        // Verificar autenticación
        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);

        if (!$user) {
            return "Hola {$userName}! Para poder procesar archivos de audio y crear transacciones, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        $baseMessage = "¡Hola {$userName}! Has enviado un archivo de audio \"{$title}\" de {$durationFormatted}.";

        try {
            $fileInfo = $this->fileService->getFileFromAudio($audioData);

            if (!$fileInfo) {
                return "{$baseMessage} He recibido tu archivo de audio correctamente, pero no pude procesarlo para crear transacciones.";
            }

            $fileSize = TelegramMessageHelper::formatFileSize($fileInfo['file_size']);

            // Verificar duración (máximo 10 minutos para procesamiento con IA)
            if ($duration > 600) {
                return "{$baseMessage} El archivo de audio es demasiado largo ({$durationFormatted}). Para procesar transacciones, por favor envía archivos de audio de máximo 10 minutos.";
            }

            // Verificar si el archivo es demasiado grande (máximo 20MB)
            if ($fileInfo['file_size'] > 20 * 1024 * 1024) {
                return "{$baseMessage} El archivo es demasiado grande ({$fileSize}). Para procesar con IA, el archivo no debe superar los 20MB.";
            }

            // Descargar audio temporalmente
            $downloadResult = $this->fileService->downloadFileTemporarily($fileInfo);

            if (!$downloadResult) {
                return "{$baseMessage} No pude descargar el archivo de audio para procesarlo. Inténtalo de nuevo.";
            }

            // Procesar audio con IA para transcripción y análisis
            $result = $this->transactionProcessor->processAudio($downloadResult['full_path'], $user);

            // Limpiar archivo temporal
            $this->cleanupTemporaryFile($downloadResult['full_path']);

            return $result;

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('audio', $e, $userName, ['audio_data' => $audioData]);
            return "Ocurrió un error al procesar el archivo de audio. Por favor, inténtalo de nuevo.";
        }
    }

    public function getPriority(): int
    {
        return 20;
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
