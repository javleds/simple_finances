<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Contracts\MessageActionDetectionServiceInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Telegram\MessageActionProcessorFactory;
use App\Enums\MessageAction;
use App\Services\Transaction\TransactionProcessorService;
use Illuminate\Support\Facades\Log;

class TextMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TransactionProcessorService $transactionProcessor,
        private readonly MessageActionDetectionServiceInterface $actionDetectionService,
        private readonly MessageActionProcessorFactory $actionProcessorFactory
    ) {}

    public static function getMessageType(): string
    {
        return 'text';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasText($telegramUpdate)
            && !$this->isCommand($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        // Verificar si el usuario está autenticado
        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);

        if (!$user) {
            return "Hola {$userName}! Para poder usar el bot, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        try {
            // Detectar la acción del mensaje usando OpenAI
            $detectionResult = $this->actionDetectionService->detectAction($messageText);

            if ($detectionResult['success'] && $detectionResult['action'] !== MessageAction::CreateTransaction->value) {
                // Crear enum desde el valor
                $action = MessageAction::from($detectionResult['action']);

                // Procesar con el procesador específico de la acción
                $actionProcessor = $this->actionProcessorFactory->getProcessor($action);

                if ($actionProcessor && $actionProcessor->canHandle($action, $detectionResult['context'] ?? [])) {
                    return $actionProcessor->process($detectionResult['context'] ?? [], $user);
                }
            }

            // Si llegamos aquí, es una creación de transacción o no se pudo procesar
            if ($this->seemsLikeTransaction($messageText) ||
                ($detectionResult['success'] && $detectionResult['action'] === MessageAction::CreateTransaction->value)) {
                return $this->transactionProcessor->processText($messageText, $user);
            }

        } catch (\Exception $e) {
            Log::error('TextMessageProcessor: Error processing message action', [
                'user_id' => $user->id,
                'message' => $messageText,
                'error' => $e->getMessage()
            ]);

            // Fallback al comportamiento anterior si hay error
            if ($this->seemsLikeTransaction($messageText)) {
                return $this->transactionProcessor->processText($messageText, $user);
            }
        }

        // Respuesta por defecto para otros mensajes de texto
        return $this->getDefaultResponse($userName);
    }

    private function getDefaultResponse(string $userName): string
    {
        return "¡Hola {$userName}! Puedo ayudarte con varias acciones:\n\n" .
               "💰 **Transacciones**: Describe tu movimiento (ej: 'Gasté 250 en supermercado')\n" .
               "📊 **Consultar saldo**: 'Cuál es mi saldo' o 'Balance de mi cuenta Santander'\n" .
               "📋 **Ver movimientos**: 'Mis últimos movimientos' o 'Historial de mi tarjeta'\n" .
               "✏️ **Modificar**: 'Modifica mi última transacción'\n" .
               "🗑️ **Eliminar**: 'Elimina mi última transacción'\n\n" .
               "¿En qué te puedo ayudar?";
    }

    public function getPriority(): int
    {
        return 10;
    }

    private function isCommand(array $telegramUpdate): bool
    {
        $text = TelegramMessageHelper::getText($telegramUpdate);
        return str_starts_with(trim($text), '/');
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
}
