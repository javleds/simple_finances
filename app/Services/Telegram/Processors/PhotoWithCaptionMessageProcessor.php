<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Telegram\TelegramFileService;
use App\Services\Transaction\TransactionProcessorService;

class PhotoWithCaptionMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TelegramFileService $fileService,
        private readonly TransactionProcessorService $transactionProcessor
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

        // Priorizar el texto del caption sobre la imagen
        if ($this->seemsLikeTransaction($caption)) {
            return $this->transactionProcessor->processText($caption, $user);
        }

        // Si el caption no parece una transacción, procesar la imagen
        try {
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

            // Procesar imagen con IA
            $result = $this->transactionProcessor->processImage($downloadResult['full_path'], $user);
            
            // Limpiar archivo temporal
            $this->cleanupTemporaryFile($downloadResult['full_path']);
            
            return $result;

        } catch (\Exception $e) {
            TelegramMessageHelper::logFileError('photo_with_caption', $e, $userName);
            return "Ocurrió un error al procesar la imagen. Si tienes información de transacción, por favor descríbela en texto.";
        }
    }

    public function getPriority(): int
    {
        return 25;
    }

    private function seemsLikeTransaction(string $text): bool
    {
        $text = mb_strtolower($text);
        
        // Palabras clave que sugieren una transacción
        $transactionKeywords = [
            'gast', 'deposit', 'ingres', 'cobr', 'pag', 'retir', 
            'compré', 'vendí', 'recibí', 'transferí', 'ahorre',
            'cuenta', 'tarjeta', 'efectivo', 'pesos', '$', 'dinero',
            'banco', 'oxxo', 'supermercado', 'gasolina', 'comida'
        ];

        foreach ($transactionKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        // También verificar si contiene números (posibles montos)
        return preg_match('/\d+/', $text);
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
