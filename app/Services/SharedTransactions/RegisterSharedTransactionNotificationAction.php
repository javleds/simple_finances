<?php

namespace App\Services\SharedTransactions;

use App\Dto\SharedTransactionNotificationDto;
use App\Enums\SharedTransactionNotificationAction;
use App\Enums\SharedTransactionNotificationBatchStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

class RegisterSharedTransactionNotificationAction
{
    public function execute(SharedTransactionNotificationDto $dto): SharedTransactionNotificationBatch
    {
        $batch = $this->findOrCreateBatch(
            recipientId: $dto->recipient->id,
            accountId: $dto->transaction->account_id,
        );

        SharedTransactionNotificationItem::create([
            'batch_id' => $batch->id,
            'account_id' => $dto->transaction->account_id,
            'transaction_id' => $dto->transaction->id,
            'modifier_id' => $dto->modifier->id,
            'action' => SharedTransactionNotificationAction::fromAction($dto->action),
            'concept' => $dto->transaction->concept,
            'type' => $dto->transaction->type,
            'amount' => $dto->transaction->amount,
            'scheduled_at' => $dto->transaction->scheduled_at,
        ]);

        return $batch;
    }

    public function executeSettlement(
        User $recipient,
        User $modifier,
        Account $account,
        float $amount,
        string $description,
        CarbonInterface $occurredAt,
    ): SharedTransactionNotificationBatch {
        $batch = $this->findOrCreateBatch(
            recipientId: $recipient->id,
            accountId: $account->id,
        );

        SharedTransactionNotificationItem::create([
            'batch_id' => $batch->id,
            'account_id' => $account->id,
            'transaction_id' => null,
            'modifier_id' => $modifier->id,
            'action' => SharedTransactionNotificationAction::Settled,
            'concept' => $description,
            'type' => TransactionType::Income,
            'amount' => round($amount, 2),
            'scheduled_at' => $occurredAt,
        ]);

        return $batch;
    }

    private function findOrCreateBatch(int $recipientId, int $accountId): SharedTransactionNotificationBatch
    {
        $now = now();
        $groupKey = $this->groupKey($recipientId);

        $batch = SharedTransactionNotificationBatch::query()
            ->where('group_key', $groupKey)
            ->where('status', SharedTransactionNotificationBatchStatus::Pending)
            ->first();

        if ($batch) {
            $batch->last_activity_at = $now;
            $batch->save();

            return $batch;
        }

        try {
            return SharedTransactionNotificationBatch::create([
                'user_id' => $recipientId,
                'account_id' => $accountId,
                'group_key' => $groupKey,
                'status' => SharedTransactionNotificationBatchStatus::Pending,
                'window_started_at' => $now,
                'last_activity_at' => $now,
            ]);
        } catch (QueryException $exception) {
            $batch = SharedTransactionNotificationBatch::query()
                ->where('group_key', $groupKey)
                ->where('status', SharedTransactionNotificationBatchStatus::Pending)
                ->first();

            if (! $batch) {
                throw $exception;
            }

            $batch->last_activity_at = $now;
            $batch->save();

            return $batch;
        }
    }

    private function groupKey(int $recipientId): string
    {
        return "shared-movements:user:{$recipientId}";
    }
}
