<?php

namespace App\Services\Transaction;

use App\Dto\TransactionExtractionDto;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TransactionDataValidator
{
    public function validateTransactionData(TransactionExtractionDto $dto, User $user): array
    {
        // Validar cuenta
        $account = $this->findUserAccount($dto->account, $user);
        if (!$account) {
            return [
                'valid' => false,
                'error' => "No encontré una cuenta con el nombre '{$dto->account}' en tu lista de cuentas. Verifica el nombre e inténtalo de nuevo.",
            ];
        }

        // Validar meta financiera (opcional)
        $financialGoal = null;
        if ($dto->financialGoal) {
            $financialGoal = $this->findUserFinancialGoal($dto->financialGoal, $user, $account);
            if (!$financialGoal) {
                return [
                    'valid' => false,
                    'error' => "No encontré una meta financiera con el nombre '{$dto->financialGoal}' para la cuenta '{$account->name}'. Verifica el nombre e inténtalo de nuevo.",
                ];
            }
        }

        // Validar monto
        if ($dto->amount <= 0) {
            return [
                'valid' => false,
                'error' => 'El monto debe ser mayor a cero. Por favor, proporciona un monto válido.',
            ];
        }

        // Validar fecha (opcional)
        if ($dto->date && !$this->isValidDate($dto->date)) {
            return [
                'valid' => false,
                'error' => "La fecha '{$dto->date}' no tiene un formato válido. Usa el formato YYYY-MM-DD o describe la fecha en palabras.",
            ];
        }

        return [
            'valid' => true,
            'account' => $account,
            'financial_goal' => $financialGoal,
        ];
    }

    private function findUserAccount(string $accountName, User $user): ?Account
    {
        // Buscar por nombre exacto primero
        $account = Account::whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('name', 'LIKE', $accountName)
            ->first();

        if ($account) {
            return $account;
        }

        // Buscar por similitud de texto
        $accounts = Account::whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($accounts as $account) {
            $similarity = $this->calculateSimilarity($accountName, $account->name);
            
            if ($similarity > $highestSimilarity && $similarity > 0.7) {
                $highestSimilarity = $similarity;
                $bestMatch = $account;
            }
        }

        Log::info('Account search result', [
            'searched' => $accountName,
            'found' => $bestMatch?->name,
            'similarity' => $highestSimilarity
        ]);

        return $bestMatch;
    }

    private function findUserFinancialGoal(string $goalName, User $user, Account $account): ?FinancialGoal
    {
        // Buscar por nombre exacto en la cuenta específica
        $goal = FinancialGoal::where('user_id', $user->id)
            ->where('account_id', $account->id)
            ->where('name', 'LIKE', $goalName)
            ->first();

        if ($goal) {
            return $goal;
        }

        // Buscar por similitud de texto
        $goals = FinancialGoal::where('user_id', $user->id)
            ->where('account_id', $account->id)
            ->get();

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($goals as $goal) {
            $similarity = $this->calculateSimilarity($goalName, $goal->name);
            
            if ($similarity > $highestSimilarity && $similarity > 0.7) {
                $highestSimilarity = $similarity;
                $bestMatch = $goal;
            }
        }

        Log::info('Financial goal search result', [
            'searched' => $goalName,
            'found' => $bestMatch?->name,
            'similarity' => $highestSimilarity,
            'account' => $account->name
        ]);

        return $bestMatch;
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = mb_strtolower(trim($str1));
        $str2 = mb_strtolower(trim($str2));

        if ($str1 === $str2) {
            return 1.0;
        }

        // Levenshtein distance para similitud
        $maxLen = max(mb_strlen($str1), mb_strlen($str2));
        if ($maxLen == 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        return 1.0 - ($distance / $maxLen);
    }

    private function isValidDate(string $date): bool
    {
        try {
            $parsedDate = \Carbon\Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}