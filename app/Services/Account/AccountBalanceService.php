<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AccountBalanceService
{
    public function __construct(
        private readonly AccountFinderService $accountFinderService
    ) {}

    public function getAccountBalance(string $accountName, User $user): ?array
    {
        try {
            $account = $this->accountFinderService->findUserAccount($accountName, $user);

            if (!$account) {
                return null;
            }

            $balance = $account->balance;

            $data = [
                'account' => $account,
                'balance' => $balance,
                'formatted_balance' => as_money($balance),
                'is_credit_card' => $account->isCreditCard(),
            ];

            // Información adicional para tarjetas de crédito
            if ($account->isCreditCard()) {
                $data['credit_line'] = $account->credit_line;
                $data['available_credit'] = $account->available_credit;
                $data['spent'] = $account->spent;
                $data['next_cutoff_date'] = $account->next_cutoff_date;
                $data['formatted_available_credit'] = as_money($account->available_credit);
                $data['formatted_spent'] = as_money(abs($account->spent));
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('AccountBalanceService: Error getting balance', [
                'account_name' => $accountName,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getAllAccountsBalance(User $user): Collection
    {
        return $user->accounts()
            ->get()
            ->map(function (Account $account) {
                $balance = $account->balance;

                return [
                    'account' => $account,
                    'balance' => $balance,
                    'formatted_balance' => as_money($balance),
                    'is_credit_card' => $account->isCreditCard(),
                    'available_credit' => $account->available_credit ?? null,
                    'formatted_available_credit' => $account->available_credit ? as_money($account->available_credit) : null,
                ];
            });
    }
}
