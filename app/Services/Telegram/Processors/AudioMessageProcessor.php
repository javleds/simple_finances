<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\MessageActionDetectionServiceInterface;
use App\Contracts\OpenAIServiceInterface;
use App\Contracts\TelegramMessageProcessorInterface;
use App\Enums\MessageAction;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Telegram\MessageActionProcessorFactory;
use App\Services\Telegram\TelegramFileService;
use Illuminate\Support\Facades\Log;

class AudioMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService,
        private readonly OpenAIServiceInterface $openAIService,
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

        if (! $user) {
            return "Hola {$userName}! Para poder procesar archivos de audio y crear transacciones, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        $baseMessage = "¡Hola {$userName}! Has enviado un archivo de audio \"{$title}\" de {$durationFormatted}.";

        try {
            $fileInfo = $this->fileService->getFileFromAudio($audioData);

            if (! $fileInfo) {
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

            return $this->processAudioFile($fileInfo, $baseMessage, $user);

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('audio', $e, $userName, ['audio_data' => $audioData]);

            return 'Ocurrió un error al procesar el archivo de audio. Por favor, inténtalo de nuevo.';
        }
    }

    public function getPriority(): int
    {
        return 20;
    }

    private function processAudioFile(array $fileInfo, string $baseMessage, $user): string
    {
        // Descargar audio temporalmente
        $downloadResult = $this->fileService->downloadFileTemporarily($fileInfo);

        if (! $downloadResult) {
            return "{$baseMessage} No pude descargar el archivo de audio para procesarlo. Inténtalo de nuevo.";
        }

        // Transcribir el audio usando OpenAI
        $transcriptionResult = $this->openAIService->transcribeAudio($downloadResult['full_path']);

        // Limpiar archivo temporal
        $this->cleanupTemporaryFile($downloadResult['full_path']);

        if (! $transcriptionResult['success']) {
            Log::error('Audio transcription failed', ['error' => $transcriptionResult['error']]);

            return "{$baseMessage} No pude transcribir el audio. Por favor, inténtalo de nuevo.";
        }

        $transcribedText = $transcriptionResult['text'];
        Log::info('AudioMessageProcessor: Audio transcribed', ['text' => $transcribedText]);

        return $this->processTranscribedText($transcribedText, $user);
    }

    private function processTranscribedText(string $transcribedText, $user): string
    {
        try {
            $detectionResult = $this->actionDetectionService->detectAction($transcribedText);

            if (! $detectionResult['success']) {
                return "🎤 **Audio transcrito**: \"{$transcribedText}\"\n\n⚠️ No pude determinar qué acción realizar con este audio. Por favor, intenta ser más específico.";
            }

            // Crear enum desde el valor
            $action = MessageAction::from($detectionResult['action']);

            // Procesar con el procesador específico de la acción
            $actionProcessor = $this->actionProcessorFactory->getProcessor($action);

            if (! $actionProcessor || ! $actionProcessor->canHandle($action, $detectionResult['context'] ?? [])) {
                return "🎤 **Audio transcrito**: \"{$transcribedText}\"\n\n⚠️ No encontré un procesador adecuado para esta acción. Por favor, intenta ser más específico.";
            }

            $result = $actionProcessor->process($detectionResult['context'] ?? [], $user);

            return "🎤 **Audio transcrito**: \"{$transcribedText}\"\n\n{$result}";

        } catch (\Exception $e) {
            Log::error('AudioMessageProcessor: Action processing failed', [
                'error' => $e->getMessage(),
                'transcribed_text' => $transcribedText,
            ]);

            return "🎤 **Audio transcrito**: \"{$transcribedText}\"\n\n⚠️ Ocurrió un error al procesar tu solicitud. Por favor, inténtalo de nuevo.";
        }
    }

    private function cleanupTemporaryFile(string $filePath): void
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup temporary file', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
