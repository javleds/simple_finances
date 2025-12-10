<?php

namespace App\Services\Telegram\Actions;

use App\Contracts\MessageActionProcessorInterface;
use App\Enums\MessageAction;
use App\Models\User;
use App\Services\Telegram\Helpers\MessageActionHelper;
use App\Services\Transaction\LastTransactionService;
use Illuminate\Support\Facades\Log;

class DeleteLastTransactionActionProcessor implements MessageActionProcessorInterface
{
    public function __construct(
        private readonly LastTransactionService $lastTransactionService
    ) {}

    public static function getActionType(): MessageAction
    {
        return MessageAction::DeleteLastTransaction;
    }

    public function canHandle(MessageAction $action, array $context = []): bool
    {
        return $action === MessageAction::DeleteLastTransaction;
    }

    public function process(array $context, User $user): string
    {
        try {
            // Obtener la última transacción del usuario
            $lastTransaction = $this->lastTransactionService->getLastUserTransaction($user);

            if (! $lastTransaction) {
                return MessageActionHelper::formatNoLastTransactionResponse();
            }

            // Verificar permisos
            if (! $this->lastTransactionService->canModifyTransaction($lastTransaction, $user)) {
                return '❌ No tienes permisos para eliminar esta transacción.';
            }

            // Procesar la eliminación
            $success = $this->lastTransactionService->deleteTransaction($lastTransaction, $user);

            if (! $success) {
                return '❌ No se pudo eliminar la transacción. Por favor, inténtalo de nuevo.';
            }

            return MessageActionHelper::formatTransactionDeletionResponse($lastTransaction);

        } catch (\Exception $e) {
            Log::error('DeleteLastTransactionActionProcessor: Error processing deletion', [
                'user_id' => $user->id,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return MessageActionHelper::formatErrorResponse('eliminar la transacción');
        }
    }

    public function getPriority(): int
    {
        return 50;
    }
}
