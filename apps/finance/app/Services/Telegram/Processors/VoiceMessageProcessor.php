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

class VoiceMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService,
        private readonly OpenAIServiceInterface $openAIService,
        private readonly MessageActionDetectionServiceInterface $actionDetectionService,
        private readonly MessageActionProcessorFactory $actionProcessorFactory
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

        if (! $user) {
            return "Hola {$userName}! Para poder procesar mensajes de voz y crear transacciones, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        auth()->login($user);

        try {
            $fileInfo = $this->fileService->getFileFromVoice($voiceData);

            if (! $fileInfo) {
                return 'No pude procesar el mensaje de voz. Por favor, inténtalo de nuevo.';
            }

            // Verificar duración (máximo 60 segundos para procesamiento con IA)
            if ($duration > 60) {
                return "El mensaje de voz es demasiado largo ({$durationFormatted}). Para procesar transacciones, por favor envía mensajes de voz de máximo 60 segundos.";
            }

            // Descargar audio temporalmente
            $downloadResult = $this->fileService->downloadFileTemporarily($fileInfo);

            if (! $downloadResult) {
                return 'No pude descargar el mensaje de voz para procesarlo. Inténtalo de nuevo.';
            }

            // Transcribir el audio usando OpenAI
            $transcriptionResult = $this->openAIService->transcribeAudio($downloadResult['full_path']);

            // Limpiar archivo temporal
            $this->cleanupTemporaryFile($downloadResult['full_path']);

            if (! $transcriptionResult['success']) {
                Log::error('Voice transcription failed', ['error' => $transcriptionResult['error']]);

                return 'No pude transcribir el mensaje de voz. Por favor, inténtalo de nuevo.';
            }

            $transcribedText = $transcriptionResult['text'];
            Log::info('VoiceMessageProcessor: Voice transcribed', ['text' => $transcribedText]);

            // Usar el sistema de detección de acciones como en TextMessageProcessor
            try {
                $detectionResult = $this->actionDetectionService->detectAction($transcribedText);

                if ($detectionResult['success']) {
                    // Crear enum desde el valor
                    $action = MessageAction::from($detectionResult['action']);

                    // Procesar con el procesador específico de la acción
                    $actionProcessor = $this->actionProcessorFactory->getProcessor($action);

                    if ($actionProcessor && $actionProcessor->canHandle($action, $detectionResult['context'] ?? [])) {
                        $result = $actionProcessor->process($detectionResult['context'] ?? [], $user);

                        return "🎙️ **Mensaje de voz transcrito**: \"{$transcribedText}\"\n\n{$result}";
                    }
                }

                // Si no se detectó acción válida, mostrar mensaje con transcripción
                return "🎙️ **Mensaje de voz transcrito**: \"{$transcribedText}\"\n\n⚠️ No pude determinar qué acción realizar con este mensaje. Por favor, intenta ser más específico.";

            } catch (\Exception $e) {
                Log::error('VoiceMessageProcessor: Action processing failed', [
                    'error' => $e->getMessage(),
                    'transcribed_text' => $transcribedText,
                ]);

                return "🎙️ **Mensaje de voz transcrito**: \"{$transcribedText}\"\n\n⚠️ Ocurrió un error al procesar tu solicitud. Por favor, inténtalo de nuevo.";
            }

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('voice', $e, $userName, ['voice_data' => $voiceData]);

            return 'Ocurrió un error al procesar el mensaje de voz. Por favor, inténtalo de nuevo.';
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
                'error' => $e->getMessage(),
            ]);
        }
    }
}
