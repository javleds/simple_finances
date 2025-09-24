<?php

namespace App\Services\Transaction;

use App\Enums\Action;
use App\Events\TransactionSaved;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LastTransactionService
{
    public function getLastUserTransaction(User $user): ?Transaction
    {
        try {
            return Transaction::whereHas('account.users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->where('user_id', $user->id) // Asegurar que la transacción fue creada por el usuario
                ->orderBy('created_at', 'desc')
                ->first();

        } catch (\Exception $e) {
            Log::error('LastTransactionService: Error getting last user transaction', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function canModifyTransaction(Transaction $transaction, User $user): bool
    {
        // El usuario puede modificar solo sus propias transacciones
        return $transaction->user_id === $user->id;
    }

    public function canDeleteTransaction(Transaction $transaction, User $user): bool
    {
        // El usuario puede eliminar solo sus propias transacciones
        return $transaction->user_id === $user->id;
    }

    public function modifyTransaction(Transaction $transaction, array $changes, User $user): bool
    {
        try {
            if (!$this->canModifyTransaction($transaction, $user)) {
                Log::warning('LastTransactionService: User attempted to modify transaction without permission', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'transaction_owner' => $transaction->user_id
                ]);
                return false;
            }

            $originalData = $transaction->toArray();

            // Aplicar cambios
            foreach ($changes as $field => $value) {
                switch ($field) {
                    case 'concept':
                        $transaction->concept = $value;
                        break;
                    case 'amount':
                        $transaction->amount = (float) $value;
                        break;
                    case 'type':
                        $transaction->type = $value;
                        break;
                    case 'scheduled_at':
                        $transaction->scheduled_at = $value;
                        break;
                    case 'account_id':
                        $transaction->account_id = $value;
                        break;
                    case 'financial_goal_id':
                        $transaction->financial_goal_id = $value;
                        break;
                }
            }

            $transaction->save();

            // Disparar evento de modificación
            event(new TransactionSaved($transaction, Action::Updated));

            Log::info('LastTransactionService: Transaction modified successfully', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'changes' => $changes
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('LastTransactionService: Error modifying transaction', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'changes' => $changes,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function deleteTransaction(Transaction $transaction, User $user): bool
    {
        try {
            if (!$this->canDeleteTransaction($transaction, $user)) {
                Log::warning('LastTransactionService: User attempted to delete transaction without permission', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'transaction_owner' => $transaction->user_id
                ]);
                return false;
            }

            $account = $transaction->account;

            $transaction->delete();
            event(new TransactionSaved($transaction, Action::Deleted));

            Log::info('LastTransactionService: Transaction deleted successfully', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'concept' => $transaction->concept,
                'amount' => $transaction->amount
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('LastTransactionService: Error deleting transaction', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
