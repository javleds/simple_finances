<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use function app;

use App\Services\Money\RequireExactMoneyStorage;

use function class_basename;

use Closure;
use Illuminate\Http\Request;

use function in_array;
use function response;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class RequireExactMoneyContract
{
    public function handle(Request $request, Closure $next): Response
    {
        $controller = class_basename($request->route()?->getControllerClass() ?? '');
        if (! in_array($controller, self::CONTROLLERS, true)) {
            return $next($request);
        }

        if ('2' !== $request->header('X-Finance-Contract')) {
            return response()->json([
                'message' => 'Actualiza la aplicación para continuar con el contrato financiero vigente.',
                'code'    => 'financial_contract_update_required',
            ], 409);
        }

        try {
            app(RequireExactMoneyStorage::class)->execute();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => 'El servicio financiero está en mantenimiento.', 'code' => 'exact_money_storage_pending'], 503);
        }

        $response = $next($request);
        $response->headers->set('X-Finance-Contract', '2');

        return $response;
    }
    private const CONTROLLERS = [
        'AccountController', 'AccountBalanceSnapshotController', 'AccountCustodianController',
        'AccountFinancialGoalController', 'AccountInviteController', 'AccountLedgerController',
        'AccountLedgerDiagnosticController', 'AccountLedgerRepairController', 'AccountMemberTransferController',
        'AccountRelationInviteController', 'AccountTransactionController', 'AccountUserController',
        'DashboardAccountController', 'DashboardGraphController', 'DashboardPeriodSummaryController',
        'DashboardSubscriptionController', 'FinancialGoalController', 'FixedIncomeController',
        'FixedOutcomeController', 'PartialFixedIncomeController', 'PersonalMoneyController',
        'SharedTransactionNotificationBatchController', 'SharedTransactionNotificationItemController',
        'SubscriptionController', 'SubscriptionPaymentController', 'TransactionController', 'VirtualAccountController',
    ];
}
