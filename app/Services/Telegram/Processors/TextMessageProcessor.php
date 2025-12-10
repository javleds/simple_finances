<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\MessageActionDetectionServiceInterface;
use App\Contracts\TelegramMessageProcessorInterface;
use App\Enums\MessageAction;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Telegram\MessageActionProcessorFactory;
use Illuminate\Support\Facades\Log;

class TextMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
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
            && ! $this->isCommand($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);

        // Verificar si el usuario está autenticado
        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);

        if (! $user) {
            return "Hola {$userName}! Para poder usar el bot, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        auth()->login($user);

        try {
            // Detectar la acción del mensaje usando OpenAI
            $detectionResult = $this->actionDetectionService->detectAction($messageText);

            if ($detectionResult['success']) {
                // Crear enum desde el valor
                $action = MessageAction::from($detectionResult['action']);

                // Procesar con el procesador específico de la acción
                $actionProcessor = $this->actionProcessorFactory->getProcessor($action);

                if ($actionProcessor && $actionProcessor->canHandle($action, $detectionResult['context'] ?? [])) {
                    return $actionProcessor->process($detectionResult['context'] ?? [], $user);
                }
            }

        } catch (\Exception $e) {
            Log::error('TextMessageProcessor: Error processing message action', [
                'user_id' => $user->id,
                'message' => $messageText,
                'error' => $e->getMessage(),
            ]);
        }

        // Respuesta por defecto para otros mensajes de texto
        return $this->getDefaultResponse($userName);
    }

    private function getDefaultResponse(string $userName): string
    {
        return "¡Hola {$userName}! Puedo ayudarte con varias acciones:\n\n".
               "💰 **Transacciones**: Describe tu movimiento (ej: 'Gasté 250 en supermercado')\n".
               "📊 **Consultar saldo**: 'Cuál es mi saldo' o 'Balance de mi cuenta Santander'\n".
               "📋 **Ver movimientos**: 'Mis últimos movimientos' o 'Historial de mi tarjeta'\n".
               "✏️ **Modificar**: 'Modifica mi última transacción'\n".
               "🗑️ **Eliminar**: 'Elimina mi última transacción'\n\n".
               '¿En qué te puedo ayudar?';
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
}
