<?php

namespace App\Services\Dashboard;

use App\Dto\TransactionFormDto;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Contracts\Auth\Guard;

class CompletePendingTransactionsBatch
{
    public function __construct(
        private Guard $auth,
        private TransactionUpdater $transactionUpdater,
    ) {}

    public function execute(array $transactionIds): array
    {
        $completedIds = [];
        $failed = [];

        foreach ($transactionIds as $transactionId) {
            $publicId = (string) $transactionId;
            $id = $this->parseTransactionId($publicId);

            if ($id === null) {
                $failed[] = $this->failedItem($publicId, 'Invalid transaction id.');

                continue;
            }

            $transaction = $this->findPendingTransaction($id);

            if (! $transaction) {
                $failed[] = $this->failedItem($publicId, 'Transaction is not pending or is not available.');

                continue;
            }

            $this->complete($transaction);
            $completedIds[] = 'tx-'.$transaction->id;
        }

        return [
            'processed' => count($completedIds),
            'failed' => $failed,
            'transaction_ids' => $completedIds,
        ];
    }

    private function parseTransactionId(string $transactionId): ?int
    {
        $normalizedId = str_starts_with($transactionId, 'tx-')
            ? substr($transactionId, 3)
            : $transactionId;

        if (! ctype_digit($normalizedId)) {
            return null;
        }

        return (int) $normalizedId;
    }

    private function findPendingTransaction(int $id): ?Transaction
    {
        return Transaction::query()
            ->whereKey($id)
            ->where('user_id', $this->auth->id())
            ->where('status', TransactionStatus::Pending)
            ->first();
    }

    private function complete(Transaction $transaction): void
    {
        $subTransactions = $transaction->subTransactions()->get();
        $userPayments = $subTransactions
            ->map(fn (Transaction $subTransaction): array => [
                'user_id' => $subTransaction->user_id,
                'percentage' => $subTransaction->percentage ?? 0.0,
            ])
            ->all();

        $this->transactionUpdater->execute($transaction, TransactionFormDto::fromFormArray([
            'id' => $transaction->id,
            'type' => $transaction->type,
            'status' => TransactionStatus::Completed,
            'concept' => $transaction->concept,
            'amount' => $transaction->amount,
            'account_id' => $transaction->account_id,
            'split_between_users' => $subTransactions->isNotEmpty(),
            'user_payments' => $userPayments,
            'scheduled_at' => $transaction->scheduled_at,
            'financial_goal_id' => $transaction->financial_goal_id,
        ]));
    }

    private function failedItem(string $id, string $message): array
    {
        return [
            'id' => $id,
            'message' => $message,
        ];
    }
}
