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

class PhotoWithCaptionMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService,
        private readonly TransactionProcessorService $transactionProcessor,
        private readonly MessageActionDetectionServiceInterface $actionDetectionService,
        private readonly MessageActionProcessorFactory $actionProcessorFactory
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
        $caption = TelegramMessageHelper::getCaption($telegramUpdate);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        // Verificar autenticación
        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);

        if (!$user) {
            return "Hola {$userName}! Para poder procesar imágenes con texto y crear transacciones, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        try {
            // Primero intentar detectar acción usando el caption
            $detectionResult = $this->actionDetectionService->detectAction($caption);

            // Si no se pudo detectar acción, procesar como transacción directamente
            if (!$detectionResult['success']) {
                return $this->processImageWithCaption($telegramUpdate, $caption, $user);
            }

            // Crear enum desde el valor
            $action = MessageAction::from($detectionResult['action']);

            // Si es crear transacción, procesar imagen con caption como contexto
            if ($action === MessageAction::CreateTransaction) {
                return $this->processImageWithCaption($telegramUpdate, $caption, $user);
            }

            // Para otras acciones, obtener el procesador específico
            $actionProcessor = $this->actionProcessorFactory->getProcessor($action);

            // Si no hay procesador disponible, procesar como transacción
            if (!$actionProcessor || !$actionProcessor->canHandle($action, $detectionResult['context'] ?? [])) {
                return $this->processImageWithCaption($telegramUpdate, $caption, $user);
            }

            // Procesar la acción específica con contexto enriquecido
            $context = $detectionResult['context'] ?? [];
            $context['original_text'] = $caption;
            $context['source_type'] = 'photo_with_caption';
            $context['has_image'] = true;

            return $actionProcessor->process($context, $user);

        } catch (\Exception $e) {
            Log::error('PhotoWithCaptionMessageProcessor: Error processing message', [
                'user_id' => $user->id,
                'caption' => $caption,
                'error' => $e->getMessage()
            ]);
            TelegramMessageHelper::logFileError('photo_with_caption', $e, $userName);
            return "Ocurrió un error al procesar la imagen con texto. Si tienes información de transacción, por favor descríbela en texto.";
        }
    }

    private function processImageWithCaption(array $telegramUpdate, string $caption, $user): string
    {
        $photos = data_get($telegramUpdate, 'message.photo', []);
        $fileInfo = $this->fileService->getFileFromPhoto($photos);

        if (!$fileInfo) {
            return "No pude procesar la imagen. Si tienes información de transacción, por favor descríbela en texto.";
        }

        // Descargar imagen temporalmente
        $downloadResult = $this->fileService->downloadFileTemporarily($fileInfo);

        if (!$downloadResult) {
            return "No pude descargar la imagen para procesarla. Si tienes información de transacción, por favor descríbela en texto.";
        }

        // Procesar imagen con IA, pasando el caption como contexto adicional
        $result = $this->transactionProcessor->processImage($downloadResult['full_path'], $caption, $user);

        // Limpiar archivo temporal
        $this->cleanupTemporaryFile($downloadResult['full_path']);

        return $result;
    }

    public function getPriority(): int
    {
        return 25;
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
                'error' => $e->getMessage()
            ]);
        }
    }
}
