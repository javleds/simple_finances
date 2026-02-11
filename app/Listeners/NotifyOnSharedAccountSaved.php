<?php

namespace App\Listeners;

use App\Events\TransactionSaved;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\SharedTransactionChangedEmail;
use App\Services\SharedTransactions\RegisterSharedTransactionNotificationAction;
use App\Dto\SharedTransactionNotificationDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyOnSharedAccountSaved
{
    /**
     * Create the event listener.
     */
    public function __construct(private RegisterSharedTransactionNotificationAction $registerSharedTransactionNotificationAction) {}

    /**
     * Handle the event.
     */
    public function handle(TransactionSaved $event): void
    {
        /** @var Collection $users */
        $users = $event->transaction->account->users()->get();
        if ($users->count() <= 1) {
            return;
        }

        $modifier = $this->resolveModifier($event->transaction->user_id);
        if (! $modifier) {
            return;
        }

        /** @var User $user */
        foreach ($users as $user) {
            if ($user->id === $modifier->id) {
                continue;
            }

            if (! $user->canReceiveNotification(NotificationType::MOVEMENTS_NOTIFICATION)) {
                continue;
            }

            if (! $user->notificableAccounts()->get()->contains($event->transaction->account)) {
                continue;
            }

            if (config('notifications.shared_transactions.mode') !== 'grouped') {
                Notification::send(
                    $user,
                    new SharedTransactionChangedEmail($user, $event->transaction, $event->action)
                );
                continue;
            }

            $this->registerSharedTransactionNotificationAction->execute(new SharedTransactionNotificationDto(
                recipient: $user,
                modifier: $modifier,
                transaction: $event->transaction,
                action: $event->action,
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
