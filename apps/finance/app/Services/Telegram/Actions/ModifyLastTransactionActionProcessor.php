<?php

namespace App\Services\Telegram\Actions;

use App\Contracts\MessageActionProcessorInterface;
use App\Contracts\OpenAIServiceInterface;
use App\Enums\MessageAction;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\User;
use App\Services\Account\AccountFinderService;
use App\Services\Telegram\Helpers\MessageActionHelper;
use App\Services\Transaction\LastTransactionService;
use Illuminate\Support\Facades\Log;

class ModifyLastTransactionActionProcessor implements MessageActionProcessorInterface
{
    public function __construct(
        private readonly LastTransactionService $lastTransactionService,
        private readonly OpenAIServiceInterface $openAIService,
        private readonly AccountFinderService $accountFinderService
    ) {}

    public static function getActionType(): MessageAction
    {
        return MessageAction::ModifyLastTransaction;
    }

    public function canHandle(MessageAction $action, array $context = []): bool
    {
        return $action === MessageAction::ModifyLastTransaction;
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
                return '❌ No tienes permisos para modificar esta transacción.';
            }

            // Mostrar información actual de la transacción
            $currentInfo = $this->formatCurrentTransactionInfo($lastTransaction);

            // Si no hay información específica de modificación en el contexto,
            // pedimos al usuario que especifique qué quiere cambiar
            if (! $this->hasModificationData($context)) {
                return $currentInfo."\n\n".
                       "💡 **Para modificar la transacción, especifica qué quieres cambiar:**\n".
                       "• \"Cambiar concepto a [nuevo concepto]\"\n".
                       "• \"Cambiar monto a [nuevo monto]\"\n".
                       "• \"Cambiar tipo a ingreso/gasto\"\n".
                       "• \"Cambiar fecha a [nueva fecha]\"\n\n".
                       '📝 También puedes escribir: "Modifica mi última transacción: [nuevos datos]"';
            }

            // Procesar la modificación
            return $this->processModification($context, $lastTransaction, $user);

        } catch (\Exception $e) {
            Log::error('ModifyLastTransactionActionProcessor: Error processing modification', [
                'user_id' => $user->id,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return MessageActionHelper::formatErrorResponse('modificar la transacción');
        }
    }

    public function getPriority(): int
    {
        return 50;
    }

    private function hasModificationData(array $context): bool
    {
        return ! empty($context['modification_text']) ||
               ! empty($context['field_to_modify']) ||
               ! empty($context['new_value']);
    }

    private function processModification(array $context, $lastTransaction, User $user): string
    {
        // Extraer texto de modificación del contexto
        $modificationText = $context['modification_text'] ?? $context['additional_info'] ?? '';

        if (empty($modificationText)) {
            return '❌ No pude entender qué quieres modificar. Por favor, especifica los cambios que deseas realizar.';
        }

        // Usar OpenAI para procesar el texto de modificación y extraer los cambios
        try {
            $response = $this->openAIService->processText($modificationText);

            if (! $response['success'] || ! $response['data']) {
                return '❌ No pude procesar los cambios solicitados. Inténtalo de nuevo con más detalles.';
            }

            $changes = $this->extractChangesFromResponse($response['data'], $lastTransaction, $user);

            if (empty($changes)) {
                return '❌ No se detectaron cambios válidos. Por favor, especifica qué quieres modificar.';
            }

            // Aplicar modificaciones
            $success = $this->lastTransactionService->modifyTransaction($lastTransaction, $changes, $user);

            if (! $success) {
                return '❌ No se pudo modificar la transacción. Por favor, inténtalo de nuevo.';
            }

            // Recargar la transacción para obtener los datos actualizados
            $lastTransaction->refresh();

            return MessageActionHelper::formatTransactionModificationResponse($lastTransaction, $changes);

        } catch (\Exception $e) {
            Log::error('ModifyLastTransactionActionProcessor: Error processing modification with OpenAI', [
                'user_id' => $user->id,
                'modification_text' => $modificationText,
                'error' => $e->getMessage(),
            ]);

            return '❌ Ocurrió un error al procesar la modificación. Inténtalo de nuevo.';
        }
    }

    private function extractChangesFromResponse(array $data, $lastTransaction, User $user): array
    {
        $changes = [];

        // Revisar cada campo y ver si cambió
        if (isset($data['concept']) && $data['concept'] !== $lastTransaction->concept) {
            $changes['concept'] = $data['concept'];
        }

        if (isset($data['amount']) && (float) $data['amount'] !== $lastTransaction->amount) {
            $changes['amount'] = (float) $data['amount'];
        }

        if (isset($data['type'])) {
            $newType = TransactionType::from($data['type']);
            if ($newType !== $lastTransaction->type) {
                $changes['type'] = $newType;
            }
        }

        if (isset($data['date']) && ! empty($data['date'])) {
            $newDate = \Carbon\Carbon::parse($data['date']);
            if ($newDate->toDateString() !== $lastTransaction->scheduled_at->toDateString()) {
                $changes['scheduled_at'] = $newDate;
            }
        }

        // Validar cambios de cuenta si se especifica
        if (isset($data['account']) && ! empty($data['account'])) {
            $account = $this->accountFinderService->findUserAccount($data['account'], $user);

            if ($account && $account->id !== $lastTransaction->account_id) {
                $changes['account_id'] = $account->id;
            }
        }

        return $changes;
    }

    private function formatCurrentTransactionInfo($transaction): string
    {
        $message = "📊 **Tu última transacción:**\n\n";
        $message .= "💼 Concepto: {$transaction->concept}\n";
        $message .= '💰 Cantidad: '.as_money($transaction->amount)."\n";
        $message .= "📝 Tipo: {$transaction->type->getLabel()}\n";
        $message .= "📅 Fecha: {$transaction->scheduled_at->format('d/m/Y')}\n";
        $message .= "🏦 Cuenta: {$transaction->account->name}";

        return $message;
    }
}
