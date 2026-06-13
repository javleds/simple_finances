<?php

namespace App\Services\Transaction;

use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionRemover
{
    public function __construct(
        private ProcessTransactionSideEffects $processTransactionSideEffects,
    ) {}

    public function execute(Transaction $transaction): array
    {
        $transaction->setRelation('account', $transaction->account()->withoutGlobalScopes()->first());
        $subTransactionIds = [];

        DB::transaction(function () use ($transaction, &$subTransactionIds): void {
            $subTransactions = Transaction::withoutGlobalScopes()
                ->where('parent_transaction_id', $transaction->id)
                ->orderBy('id')
                ->get();

            $subTransactionIds = $subTransactions
                ->pluck('id')
                ->map(fn (int $id): int => $id)
                ->all();

            $pendingSubTransactions = $subTransactions->where('status', TransactionStatus::Pending);
            $completedSubTransactions = $subTransactions->where('status', TransactionStatus::Completed);

            foreach ($pendingSubTransactions as $subTransaction) {
                $subTransaction->delete();
            }

            foreach ($completedSubTransactions as $subTransaction) {
                $subTransaction->parent_transaction_id = null;
                $subTransaction->save();
            }

            $transaction->delete();
        });

        $this->processTransactionSideEffects->execute($transaction, Action::Deleted);

        return $subTransactionIds;
    }
}
