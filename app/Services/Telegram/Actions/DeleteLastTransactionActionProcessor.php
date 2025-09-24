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

            if (!$lastTransaction) {
                return MessageActionHelper::formatNoLastTransactionResponse();
            }

            // Verificar permisos
            if (!$this->lastTransactionService->canModifyTransaction($lastTransaction, $user)) {
                return "❌ No tienes permisos para eliminar esta transacción.";
            }

            // Verificar si hay confirmación en el contexto
            if (!$this->hasConfirmation($context)) {
                return $this->formatConfirmationRequest($lastTransaction);
            }

            // Procesar la eliminación
            $success = $this->lastTransactionService->deleteTransaction($lastTransaction, $user);

            if (!$success) {
                return "❌ No se pudo eliminar la transacción. Por favor, inténtalo de nuevo.";
            }

            return MessageActionHelper::formatTransactionDeletionResponse($lastTransaction);

        } catch (\Exception $e) {
            Log::error('DeleteLastTransactionActionProcessor: Error processing deletion', [
                'user_id' => $user->id,
                'context' => $context,
                'error' => $e->getMessage()
            ]);

            return MessageActionHelper::formatErrorResponse('eliminar la transacción');
        }
    }

    public function getPriority(): int
    {
        return 50;
    }

    private function hasConfirmation(array $context): bool
    {
        $confirmationWords = ['sí', 'si', 'yes', 'confirmo', 'confirm', 'ok', 'vale', 'eliminar'];

        if (isset($context['confirmation'])) {
            return in_array(strtolower($context['confirmation']), $confirmationWords);
        }

        // Buscar palabras de confirmación en el texto adicional
        if (isset($context['additional_info'])) {
            $additionalText = strtolower($context['additional_info']);
            return collect($confirmationWords)->some(function ($word) use ($additionalText) {
                return str_contains($additionalText, $word);
            });
        }

        return false;
    }

    private function formatConfirmationRequest($transaction): string
    {
        $message = "⚠️ **¿Estás seguro que quieres eliminar esta transacción?**\n\n";
        $message .= "📊 **Transacción a eliminar:**\n";
        $message .= "💼 Concepto: {$transaction->concept}\n";
        $message .= "💰 Cantidad: " . as_money($transaction->amount) . "\n";
        $message .= "📝 Tipo: {$transaction->type->getLabel()}\n";
        $message .= "📅 Fecha: {$transaction->scheduled_at->format('d/m/Y')}\n";
        $message .= "🏦 Cuenta: {$transaction->account->name}\n\n";
        $message .= "💡 **Para confirmar, responde:**\n";
        $message .= "• \"Sí, eliminar mi última transacción\"\n";
        $message .= "• \"Confirmo eliminar\"\n";
        $message .= "• O simplemente \"Sí\"";

        return $message;
    }
}
