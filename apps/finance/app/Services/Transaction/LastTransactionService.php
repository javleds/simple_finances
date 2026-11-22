<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;

use function array_flip;
use function array_intersect_key;
use function array_replace;

use Exception;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Queries\UserOwnedResources;
use FinSi\FinanceDomain\Queries\VisibleTransactionsForUser;
use Illuminate\Support\Facades\Log;
use Throwable;

class LastTransactionService
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly AuthorizeUserOwnedResource $ownership,
        private readonly VisibleTransactionsForUser $visibleTransactions,
        private readonly TransactionUpdater $transactionUpdater,
        private readonly TransactionRemover $transactionRemover,
    ) {
    }

    public function getLastUserTransaction(User $user): ?Transaction
    {
        try {
            return $this->userOwnedResources->constrain($this->visibleTransactions->query($user->id), $user->id)
                ->completed()
                ->orderBy('created_at', 'desc')
                ->first();
        } catch (Throwable $e) {
            Log::error('LastTransactionService: Error getting last user transaction', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function canModifyTransaction(Transaction $transaction, User $user): bool
    {
        // El usuario puede modificar solo sus propias transacciones
        return $this->ownership->isOwned($transaction, $user->id);
    }

    public function canDeleteTransaction(Transaction $transaction, User $user): bool
    {
        // El usuario puede eliminar solo sus propias transacciones
        return $this->ownership->isOwned($transaction, $user->id);
    }

    public function modifyTransaction(Transaction $transaction, array $changes, User $user): bool
    {
        try {
            if (! $this->canModifyTransaction($transaction, $user)) {
                Log::warning('LastTransactionService: User attempted to modify transaction without permission', [
                    'user_id'           => $user->id,
                    'transaction_id'    => $transaction->id,
                    'transaction_owner' => $transaction->user_id,
                ]);

                return false;
            }

            $payload = [
                'id'                  => $transaction->id,
                'account_id'          => $transaction->account_id,
                'type'                => $transaction->type,
                'status'              => $transaction->status,
                'concept'             => $transaction->concept,
                'amount'              => $transaction->amount,
                'paid_by_user_id'     => $transaction->paid_by_user_id,
                'custodian_user_id'   => $transaction->custodian_user_id,
                'payment_source'      => $transaction->payment_source,
                'scheduled_at'        => $transaction->scheduled_at,
                'financial_goal_id'   => $transaction->financial_goal_id,
                'split_between_users' => $transaction->allocations->isNotEmpty(),
                'user_payments'       => $transaction->allocations->map(fn ($allocation): array => [
                    'user_id'    => $allocation->user_id,
                    'percentage' => $allocation->percentage,
                ])->all(),
            ];
            $allowedChanges = array_intersect_key($changes, array_flip([
                'concept', 'amount', 'type', 'scheduled_at', 'account_id', 'financial_goal_id',
            ]));
            $this->transactionUpdater->execute($transaction, TransactionFormDto::fromFormArray(array_replace($payload, $allowedChanges)));

            Log::info('LastTransactionService: Transaction modified successfully', [
                'user_id'        => $user->id,
                'transaction_id' => $transaction->id,
                'changes'        => $changes,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('LastTransactionService: Error modifying transaction', [
                'user_id'        => $user->id,
                'transaction_id' => $transaction->id,
                'changes'        => $changes,
                'error'          => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteTransaction(Transaction $transaction, User $user): bool
    {
        try {
            if (! $this->canDeleteTransaction($transaction, $user)) {
                Log::warning('LastTransactionService: User attempted to delete transaction without permission', [
                    'user_id'           => $user->id,
                    'transaction_id'    => $transaction->id,
                    'transaction_owner' => $transaction->user_id,
                ]);

                return false;
            }

            $this->transactionRemover->execute($transaction);

            Log::info('LastTransactionService: Transaction deleted successfully', [
                'user_id'        => $user->id,
                'transaction_id' => $transaction->id,
                'concept'        => $transaction->concept,
                'amount'         => $transaction->amount,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('LastTransactionService: Error deleting transaction', [
                'user_id'        => $user->id,
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return false;
        }
    }
}
