<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Telegram\TelegramFileService;
use App\Services\Transaction\TransactionProcessorService;

class VoiceMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService,
        private readonly TransactionProcessorService $transactionProcessor
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
        
        // Verificar autenticación
        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);
        
        if (!$user) {
            return "Hola {$userName}! Para poder procesar mensajes de voz y crear transacciones, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        try {
            $fileInfo = $this->fileService->getFileFromVoice($voiceData);

            if (!$fileInfo) {
                return "No pude procesar el mensaje de voz. Por favor, inténtalo de nuevo.";
            }

            // Verificar duración (máximo 60 segundos para procesamiento con IA)
            if ($duration > 60) {
                return "El mensaje de voz es demasiado largo ({$durationFormatted}). Para procesar transacciones, por favor envía mensajes de voz de máximo 60 segundos.";
            }

            // Descargar audio temporalmente
            $downloadResult = $this->fileService->downloadFileTemporarily($fileInfo);
            
            if (!$downloadResult) {
                return "No pude descargar el mensaje de voz para procesarlo. Inténtalo de nuevo.";
            }

            // Procesar audio con IA
            $result = $this->transactionProcessor->processAudio($downloadResult['full_path'], $user);
            
            // Limpiar archivo temporal
            $this->cleanupTemporaryFile($downloadResult['full_path']);
            
            return $result;

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('voice', $e, $userName, ['voice_data' => $voiceData]);
            return "Ocurrió un error al procesar el mensaje de voz. Por favor, inténtalo de nuevo.";
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
