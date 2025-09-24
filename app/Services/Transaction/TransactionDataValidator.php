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
        $searchTerm = trim($accountName);

        // Estrategia 1: Búsqueda exacta
        $account = $this->findExactAccount($searchTerm, $user);
        if ($account) {
            Log::info('Account found - Exact match', ['searched' => $searchTerm, 'found' => $account->name]);
            return $account;
        }

        // Estrategia 2: Búsqueda case-insensitive
        $account = $this->findCaseInsensitiveAccount($searchTerm, $user);
        if ($account) {
            Log::info('Account found - Case insensitive', ['searched' => $searchTerm, 'found' => $account->name]);
            return $account;
        }

        // Estrategia 3: Búsqueda por palabras clave parciales
        $account = $this->findPartialAccount($searchTerm, $user);
        if ($account) {
            Log::info('Account found - Partial match', ['searched' => $searchTerm, 'found' => $account->name]);
            return $account;
        }

        // Estrategia 4: Búsqueda con Soundex
        $account = $this->findSoundexAccount($searchTerm, $user);
        if ($account) {
            Log::info('Account found - Soundex match', ['searched' => $searchTerm, 'found' => $account->name]);
            return $account;
        }

        // Estrategia 5: Búsqueda por similitud de texto (Levenshtein)
        $account = $this->findSimilarAccount($searchTerm, $user);
        if ($account) {
            Log::info('Account found - Similarity match', ['searched' => $searchTerm, 'found' => $account->name]);
            return $account;
        }

        Log::info('Account not found', ['searched' => $searchTerm]);
        return null;
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

    private function findExactAccount(string $searchTerm, User $user): ?Account
    {
        return Account::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->where('name', $searchTerm)
        ->first();
    }

    private function findCaseInsensitiveAccount(string $searchTerm, User $user): ?Account
    {
        return Account::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereRaw('LOWER(name) = LOWER(?)', [$searchTerm])
        ->first();
    }

    private function findPartialAccount(string $searchTerm, User $user): ?Account
    {
        // Buscar si el término aparece dentro del nombre de la cuenta
        $account = Account::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $searchTerm . '%'])
        ->first();

        if ($account) return $account;

        // Buscar si alguna palabra del término aparece en el nombre
        $words = explode(' ', $searchTerm);
        foreach ($words as $word) {
            if (strlen($word) >= 3) { // Solo palabras de 3+ caracteres
                $account = Account::whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $word . '%'])
                ->first();

                if ($account) return $account;
            }
        }

        return null;
    }

    private function findSoundexAccount(string $searchTerm, User $user): ?Account
    {
        // Para SQLite, usamos una implementación alternativa ya que no tiene SOUNDEX nativo
        $accounts = Account::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        foreach ($accounts as $account) {
            if ($this->soundexMatch($searchTerm, $account->name)) {
                return $account;
            }
        }

        return null;
    }

    private function findSimilarAccount(string $searchTerm, User $user): ?Account
    {
        $accounts = Account::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($accounts as $account) {
            $similarity = $this->calculateSimilarity($searchTerm, $account->name);

            if ($similarity > $highestSimilarity && $similarity > 0.6) {
                $highestSimilarity = $similarity;
                $bestMatch = $account;
            }
        }

        return $bestMatch;
    }

    private function soundexMatch(string $str1, string $str2): bool
    {
        // Normalizar strings removiendo acentos y convirtiendo a minúsculas
        $normalized1 = $this->normalizeString($str1);
        $normalized2 = $this->normalizeString($str2);

        // Usar soundex PHP nativo
        return soundex($normalized1) === soundex($normalized2);
    }

    private function normalizeString(string $str): string
    {
        // Remover acentos y caracteres especiales
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        // Convertir a minúsculas y remover espacios extra
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $str)));
    }
}
