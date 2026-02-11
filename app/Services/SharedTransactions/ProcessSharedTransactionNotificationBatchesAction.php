<?php

namespace App\Services\SharedTransactions;

use App\Enums\SharedTransactionNotificationBatchStatus;
use App\Models\NotificationType;
use App\Models\SharedTransactionNotificationBatch;
use App\Notifications\SharedTransactionBatchChangedEmail;
use Illuminate\Database\Eloquent\Collection;

class ProcessSharedTransactionNotificationBatchesAction
{
    public function execute(): void
    {
        if (config('notifications.shared_transactions.mode') !== 'grouped') {
            return;
        }

        $debounceMinutes = (int) config('notifications.shared_transactions.debounce_minutes');
        if ($debounceMinutes <= 0) {
            return;
        }

        $threshold = now()->subMinutes($debounceMinutes);

        SharedTransactionNotificationBatch::query()
            ->where('status', SharedTransactionNotificationBatchStatus::Pending)
            ->where('last_activity_at', '<=', $threshold)
            ->orderBy('id')
            ->chunkById(50, function (Collection $batches): void {
                foreach ($batches as $batch) {
                    $this->processBatch($batch->id);
                }
            });
    }

    private function processBatch(int $batchId): void
    {
        $updated = SharedTransactionNotificationBatch::query()
            ->where('id', $batchId)
            ->where('status', SharedTransactionNotificationBatchStatus::Pending)
            ->update([
                'status' => SharedTransactionNotificationBatchStatus::Processing,
            ]);

        if ($updated === 0) {
            return;
        }

        $batch = SharedTransactionNotificationBatch::query()
            ->with([
                'items' => fn ($query) => $query->orderBy('id'),
                'items.modifier',
                'account',
                'user',
            ])
            ->find($batchId);

        if (! $batch) {
            return;
        }

        $user = $batch->user;
        if (! $user) {
            $this->markAsSent($batch);
            return;
        }

        $account = $batch->account;
        if (! $account) {
            $this->markAsSent($batch);
            return;
        }

        if (! $user->canReceiveNotification(NotificationType::MOVEMENTS_NOTIFICATION)) {
            $this->markAsSent($batch);
            return;
        }

        $notificableAccounts = $user->notificableAccounts()->get();
        if (! $notificableAccounts->contains($account)) {
            $this->markAsSent($batch);
            return;
        }

        if ($batch->items->isEmpty()) {
            $this->markAsSent($batch);
            return;
        }

        $user->notify(new SharedTransactionBatchChangedEmail($user, $account, $batch->items));

        $this->markAsSent($batch);
    }

    private function markAsSent(SharedTransactionNotificationBatch $batch): void
    {
        $batch->status = SharedTransactionNotificationBatchStatus::Sent;
        $batch->sent_at = now();
        $batch->save();
    }
}
