<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TransactionHistoryService
{
    public function getRecentTransactions(string $accountName, User $user, int $limit = 5): ?Collection
    {
        try {
            $account = $this->findAccountByName($accountName, $user);
            
            if (!$account) {
                return null;
            }

            return $account->transactions()
                ->with('user', 'account')
                ->orderBy('scheduled_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
                
        } catch (\Exception $e) {
            Log::error('TransactionHistoryService: Error getting recent transactions', [
                'account_name' => $accountName,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getRecentTransactionsAllAccounts(User $user, int $limit = 10): Collection
    {
        try {
            return Transaction::whereHas('account.users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with('user', 'account')
                ->orderBy('scheduled_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
                
        } catch (\Exception $e) {
            Log::error('TransactionHistoryService: Error getting recent transactions for all accounts', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    public function getAccountTransactionSummary(Account $account): array
    {
        try {
            $totalIncome = $account->transactions()->income()->sum('amount');
            $totalOutcome = $account->transactions()->outcome()->sum('amount');
            $transactionCount = $account->transactions()->count();
            $lastTransaction = $account->transactions()
                ->orderBy('scheduled_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            return [
                'total_income' => $totalIncome,
                'total_outcome' => $totalOutcome,
                'net_balance' => $totalIncome - $totalOutcome,
                'transaction_count' => $transactionCount,
                'last_transaction' => $lastTransaction,
                'formatted_income' => as_money($totalIncome),
                'formatted_outcome' => as_money($totalOutcome),
                'formatted_net' => as_money($totalIncome - $totalOutcome),
            ];
        } catch (\Exception $e) {
            Log::error('TransactionHistoryService: Error getting account summary', [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function findAccountByName(string $accountName, User $user): ?Account
    {
        $normalizedName = strtolower(trim($accountName));
        
        return $user->accounts()
            ->whereRaw('LOWER(name) LIKE ?', ["%{$normalizedName}%"])
            ->first() ?: 
            $user->accounts()
                ->whereRaw('LOWER(name) = ?', [$normalizedName])
                ->first();
    }
}