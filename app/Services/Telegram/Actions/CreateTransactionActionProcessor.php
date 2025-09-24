<?php

namespace App\Services\Telegram\Actions;

use App\Contracts\MessageActionProcessorInterface;
use App\Enums\MessageAction;
use App\Models\User;
use App\Services\Transaction\TransactionProcessorService;
use Illuminate\Support\Facades\Log;

class CreateTransactionActionProcessor implements MessageActionProcessorInterface
{
    public function __construct(
        private readonly TransactionProcessorService $transactionProcessor
    ) {}

    public static function getActionType(): MessageAction
    {
        return MessageAction::CreateTransaction;
    }

    public function canHandle(MessageAction $action, array $context = []): bool
    {
        return $action === MessageAction::CreateTransaction;
    }

    public function process(array $context, User $user): string
    {
        try {
            // Obtener el texto original del mensaje desde el contexto
            $messageText = $context['original_text'] ?? $context['text'] ?? '';

            if (empty($messageText)) {
                return "❌ No pude procesar la transacción. El mensaje está vacío.";
            }

            // Usar el servicio existente de procesamiento de transacciones
            return $this->transactionProcessor->processText($messageText, $user);

        } catch (\Exception $e) {
            Log::error('CreateTransactionActionProcessor: Error processing transaction creation', [
                'user_id' => $user->id,
                'context' => $context,
                'error' => $e->getMessage()
            ]);

            return "⚠️ Ocurrió un error al procesar la transacción. Por favor, inténtalo de nuevo más tarde.";
        }
    }

    public function getPriority(): int
    {
        return 30; // Prioridad media, después de consultas específicas pero antes que operaciones de modificación
    }
}
