<?php

namespace App\Services\Transaction;

use App\Enums\Action;
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
        DB::transaction(function () use ($transaction): void {
            $subTransactions = Transaction::withoutGlobalScopes()
                ->where('parent_transaction_id', $transaction->id)
                ->orderBy('id')
                ->get();

            $subTransactions->each->delete();
            $transaction->allocations()->delete();
            $transaction->ledgerEntries()->delete();

            $transaction->delete();
        });

        $this->processTransactionSideEffects->execute($transaction, Action::Deleted);

        return [];
    }
}
