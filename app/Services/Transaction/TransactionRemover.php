<?php

namespace App\Services\Transaction;

use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Events\TransactionSaved;
use App\Models\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class TransactionRemover
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function execute(Transaction $transaction): void
    {
        $transaction->setRelation('account', $transaction->account()->withoutGlobalScopes()->first());

        DB::transaction(function () use ($transaction) {
            $pendingSubTransactions = $transaction->subTransactions()->where('status', TransactionStatus::Pending)->get();
            $completedSubTransactions = $transaction->subTransactions()->where('status', TransactionStatus::Completed)->get();

            foreach ($pendingSubTransactions as $subTransaction) {
                $subTransaction->delete();
            }

            foreach ($completedSubTransactions as $subTransaction) {
                $subTransaction->parent_transaction_id = null;
                $subTransaction->save();
            }

            $transaction->delete();
        });

        $this->dispatcher->dispatch(new TransactionSaved($transaction, Action::Deleted));
    }
}
