<?php

namespace App\Services\SharedTransactions;

use App\Dto\SharedTransactionNotificationDto;
use App\Enums\SharedTransactionNotificationAction;
use App\Enums\SharedTransactionNotificationBatchStatus;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use Illuminate\Database\QueryException;

class RegisterSharedTransactionNotificationAction
{
    public function execute(SharedTransactionNotificationDto $dto): SharedTransactionNotificationBatch
    {
        $now = now();

        $batch = SharedTransactionNotificationBatch::query()
            ->where('user_id', $dto->recipient->id)
            ->where('account_id', $dto->transaction->account_id)
            ->where('status', SharedTransactionNotificationBatchStatus::Pending)
            ->first();

        if (! $batch) {
            try {
                $batch = SharedTransactionNotificationBatch::create([
                    'user_id' => $dto->recipient->id,
                    'account_id' => $dto->transaction->account_id,
                    'status' => SharedTransactionNotificationBatchStatus::Pending,
                    'window_started_at' => $now,
                    'last_activity_at' => $now,
                ]);
            } catch (QueryException $exception) {
                $batch = SharedTransactionNotificationBatch::query()
                    ->where('user_id', $dto->recipient->id)
                    ->where('account_id', $dto->transaction->account_id)
                    ->where('status', SharedTransactionNotificationBatchStatus::Pending)
                    ->first();

                if (! $batch) {
                    throw $exception;
                }
            }
        }

        $batch->last_activity_at = $now;
        $batch->save();

        SharedTransactionNotificationItem::create([
            'batch_id' => $batch->id,
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
}
