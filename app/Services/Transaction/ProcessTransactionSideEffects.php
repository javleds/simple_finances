<?php

namespace App\Services\Transaction;

use App\Dto\SharedTransactionNotificationDto;
use App\Enums\Action;
use App\Models\NotificationType;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SharedTransactionChangedEmail;
use App\Services\Accounts\RecalculateAccountBalance;
use App\Services\FinancialGoals\RecalculateFinancialGoalProgress;
use App\Services\SharedTransactions\RegisterSharedTransactionNotificationAction;
use Illuminate\Support\Facades\Notification;

class ProcessTransactionSideEffects
{
    public function __construct(
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
        private readonly RecalculateFinancialGoalProgress $recalculateFinancialGoalProgress,
        private readonly RegisterSharedTransactionNotificationAction $registerSharedTransactionNotificationAction,
    ) {}

    public function execute(Transaction $transaction, Action $action): void
    {
        $account = $transaction->account()->withoutGlobalScopes()->first();

        if ($account === null) {
            return;
        }

        $transaction->setRelation('account', $account);

        $this->recalculateAccountBalance->execute($account);
        $this->recalculateFinancialGoals($transaction);
        $this->notifySharedUsers($transaction, $action);
    }

    private function recalculateFinancialGoals(Transaction $transaction): void
    {
        foreach ($transaction->account->financialGoals()->withoutGlobalScopes()->get() as $goal) {
            $this->recalculateFinancialGoalProgress->execute($goal);
        }
    }

    private function notifySharedUsers(Transaction $transaction, Action $action): void
    {
        $users = $transaction->account->users()->get();

        if ($users->count() <= 1) {
            return;
        }

        $modifier = $this->resolveModifier($transaction->user_id);

        if (! $modifier) {
            return;
        }

        foreach ($users as $user) {
            if ($user->id === $modifier->id) {
                continue;
            }

            if (! $user->canReceiveNotification(NotificationType::MOVEMENTS_NOTIFICATION)) {
                continue;
            }

            if (! $user->notificableAccounts()->get()->contains($transaction->account)) {
                continue;
            }

            if (config('notifications.shared_transactions.mode') !== 'grouped') {
                Notification::send(
                    $user,
                    new SharedTransactionChangedEmail(
                        $user,
                        $transaction,
                        $action,
                        request()->is('api/*'),
                    ),
                );

                continue;
            }

            $this->registerSharedTransactionNotificationAction->execute(new SharedTransactionNotificationDto(
                recipient: $user,
                modifier: $modifier,
                transaction: $transaction,
                action: $action,
            ));
        }
    }

    private function resolveModifier(int $userId): ?User
    {
        $user = auth()->user();

        if ($user) {
            return $user;
        }

        return User::withoutGlobalScopes()->find($userId);
    }
}
