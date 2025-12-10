<?php

namespace App\Services\Telegram\Actions;

use App\Contracts\MessageActionProcessorInterface;
use App\Enums\MessageAction;
use App\Models\User;
use App\Services\Account\AccountFinderService;
use App\Services\Account\TransactionHistoryService;
use App\Services\Telegram\Helpers\MessageActionHelper;
use Illuminate\Support\Facades\Log;

class RecentTransactionsActionProcessor implements MessageActionProcessorInterface
{
    public function __construct(
        private readonly TransactionHistoryService $transactionHistoryService,
        private readonly AccountFinderService $accountFinderService
    ) {}

    public static function getActionType(): MessageAction
    {
        return MessageAction::QueryRecentTransactions;
    }

    public function canHandle(MessageAction $action, array $context = []): bool
    {
        return $action === MessageAction::QueryRecentTransactions;
    }

    public function process(array $context, User $user): string
    {
        try {
            $accountName = $context['account_name'] ?? null;
            $limit = $context['limit'] ?? 5;

            // Si no se especifica cuenta, mostrar movimientos de todas las cuentas
            if (empty($accountName)) {
                return $this->processAllAccountsTransactions($user, $limit);
            }

            return $this->processSingleAccountTransactions($accountName, $user, $limit);

        } catch (\Exception $e) {
            Log::error('RecentTransactionsActionProcessor: Error processing transactions query', [
                'user_id' => $user->id,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return MessageActionHelper::formatErrorResponse('consultar los movimientos');
        }
    }

    public function getPriority(): int
    {
        return 50;
    }

    private function processSingleAccountTransactions(string $accountName, User $user, int $limit): string
    {
        $transactions = $this->transactionHistoryService->getRecentTransactions($accountName, $user, $limit);

        if (is_null($transactions)) {
            return MessageActionHelper::formatNoAccountFoundResponse($accountName);
        }

        // Necesitamos obtener la cuenta para el formato de respuesta
        $account = $this->accountFinderService->findUserAccount($accountName, $user);

        if (! $account) {
            return MessageActionHelper::formatNoAccountFoundResponse($accountName);
        }

        return MessageActionHelper::formatTransactionHistoryResponse($transactions, $account);
    }

    private function processAllAccountsTransactions(User $user, int $limit): string
    {
        $transactions = $this->transactionHistoryService->getRecentTransactionsAllAccounts($user, $limit);

        if ($transactions->isEmpty()) {
            return "📝 No tienes movimientos registrados aún.\n\n".
                   '💡 Tip: Comienza creando transacciones para gestionar tus finanzas.';
        }

        $message = "📋 **Últimos {$limit} movimientos (todas las cuentas)**\n\n";

        $transactions->each(function ($transaction, $index) use (&$message) {
            $number = $index + 1;
            $type = $transaction->type->getLabel();
            $typeIcon = $transaction->type->name === 'Income' ? '💰' : '💸';
            $amount = as_money($transaction->amount);
            $date = $transaction->scheduled_at->format('d/m/Y');
            $userName = $transaction->user->name;
            $accountName = $transaction->account->name;

            $message .= "{$number}. {$typeIcon} **{$transaction->concept}**\n";
            $message .= "   {$type}: {$amount}\n";
            $message .= "   🏦 {$accountName} | 📅 {$date} | 👤 {$userName}\n\n";
        });

        return rtrim($message);
    }
}
