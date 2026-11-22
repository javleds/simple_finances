<?php

declare(strict_types=1);

namespace App\Services\Telegram\Actions;

use App\Contracts\MessageActionProcessorInterface;
use App\Enums\MessageAction;
use App\Services\Account\AccountBalanceService;
use App\Services\Telegram\Helpers\MessageActionHelper;

use function as_money;

use Exception;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Facades\Log;

class BalanceQueryActionProcessor implements MessageActionProcessorInterface
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
    ) {
    }

    public static function getActionType(): MessageAction
    {
        return MessageAction::QueryBalance;
    }

    public function canHandle(MessageAction $action, array $context = []): bool
    {
        return MessageAction::QueryBalance === $action;
    }

    public function process(array $context, User $user): string
    {
        try {
            $accountName = $context['account_name'] ?? null;

            // Si no se especifica cuenta, mostrar balance de todas las cuentas
            if (empty($accountName)) {
                return $this->processAllAccountsBalance($user);
            }

            return $this->processSingleAccountBalance($accountName, $user);
        } catch (Exception $e) {
            Log::error('BalanceQueryActionProcessor: Error processing balance query', [
                'user_id' => $user->id,
                'context' => $context,
                'error'   => $e->getMessage(),
            ]);

            return MessageActionHelper::formatErrorResponse('consultar el balance');
        }
    }

    public function getPriority(): int
    {
        return 50;
    }

    private function processSingleAccountBalance(string $accountName, User $user): string
    {
        $balanceData = $this->accountBalanceService->getAccountBalance($accountName, $user);

        if (! $balanceData) {
            return MessageActionHelper::formatNoAccountFoundResponse($accountName);
        }

        return MessageActionHelper::formatBalanceResponse($balanceData);
    }

    private function processAllAccountsBalance(User $user): string
    {
        $accountsBalance = $this->accountBalanceService->getAllAccountsBalance($user);

        if ($accountsBalance->isEmpty()) {
            return "❌ No tienes cuentas registradas aún.\n\n" .
                   '💡 Tip: Crea tu primera cuenta para comenzar a gestionar tus finanzas.';
        }

        $message = "💰 **Balance de todas tus cuentas**\n\n";

        $totalBalance = '0.00';

        $accountsBalance->each(function (array $data) use (&$message, &$totalBalance) {
            $account = $data['account'];
            $balance = $data['formatted_balance'];

            if ($data['is_credit_card']) {
                $message .= "💳 {$account->name}: {$balance}\n";
                $message .= "   Crédito disponible: {$data['formatted_available_credit']}\n\n";
            } else {
                $message .= "🏦 {$account->name}: {$balance}\n\n";
                $totalBalance = Decimal::add($totalBalance, $data['balance']);
            }
        });

        if (Decimal::compare($totalBalance, '0.00') > 0) {
            $message .= '💰 **Total en cuentas regulares**: ' . as_money($totalBalance);
        }

        return $message;
    }
}
