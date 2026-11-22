<?php

declare(strict_types=1);

namespace App\Services\Account;

use function as_money;
use function collect;

use Exception;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Queries\VisibleTransactionsForUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionHistoryService
{
    public function __construct(
        private readonly VisibleTransactionsForUser $visibleTransactions,
        private readonly AccountFinderService $accountFinderService,
    ) {
    }

    public function getRecentTransactions(string $accountName, User $user, int $limit = 5): ?Collection
    {
        try {
            $account = $this->accountFinderService->findUserAccount($accountName, $user);

            if (! $account) {
                return null;
            }

            return $account->transactions()
                ->completed()
                ->with('user', 'account')
                ->orderBy('scheduled_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (Throwable $e) {
            Log::error('TransactionHistoryService: Error getting recent transactions', [
                'account_name' => $accountName,
                'user_id'      => $user->id,
                'error'        => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getRecentTransactionsAllAccounts(User $user, int $limit = 10): Collection
    {
        try {
            return $this->visibleTransactions->query($user->id)
                ->completed()
                ->with('user', 'account')
                ->orderBy('scheduled_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (Throwable $e) {
            Log::error('TransactionHistoryService: Error getting recent transactions for all accounts', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return collect();
        }
    }

    public function getAccountTransactionSummary(Account $account): array
    {
        try {
            $totalIncome = Decimal::normalize((string) $account->transactions()->completed()->income()->sum('amount'));
            $totalOutcome = Decimal::normalize((string) $account->transactions()->completed()->outcome()->sum('amount'));
            $transactionCount = $account->transactions()->completed()->count();
            $lastTransaction = $account->transactions()
                ->completed()
                ->orderBy('scheduled_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            return [
                'total_income'      => Decimal::normalize((string) $totalIncome),
                'total_outcome'     => Decimal::normalize((string) $totalOutcome),
                'net_balance'       => Decimal::subtract((string) $totalIncome, (string) $totalOutcome),
                'transaction_count' => $transactionCount,
                'last_transaction'  => $lastTransaction,
                'formatted_income'  => as_money($totalIncome),
                'formatted_outcome' => as_money($totalOutcome),
                'formatted_net'     => as_money(Decimal::subtract((string) $totalIncome, (string) $totalOutcome)),
            ];
        } catch (Exception $e) {
            Log::error('TransactionHistoryService: Error getting account summary', [
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);

            return [];
        }
    }
}
