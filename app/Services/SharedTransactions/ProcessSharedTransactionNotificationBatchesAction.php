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
                'items.account',
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

        if (! $user->canReceiveNotification(NotificationType::MOVEMENTS_NOTIFICATION)) {
            $this->markAsSent($batch);
            return;
        }

        $notificableAccounts = $user->notificableAccounts()->get();
        $items = $batch->items
            ->map(function ($item) use ($batch) {
                if ($item->account === null && $batch->account !== null) {
                    $item->account_id = $batch->account->id;
                    $item->setRelation('account', $batch->account);
                }

                return $item;
            })
            ->filter(fn ($item): bool => $item->account !== null)
            ->filter(fn ($item): bool => $notificableAccounts->contains($item->account))
            ->values();

        if ($items->isEmpty()) {
            $this->markAsSent($batch);
            return;
        }

        $user->notify(new SharedTransactionBatchChangedEmail($user, $items));

        $this->markAsSent($batch);
    }

    private function markAsSent(SharedTransactionNotificationBatch $batch): void
    {
        $batch->status = SharedTransactionNotificationBatchStatus::Sent;
        $batch->group_key = null;
        $batch->sent_at = now();
        $batch->save();
    }
}
