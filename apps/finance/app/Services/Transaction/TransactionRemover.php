<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use FinSi\FinanceDomain\Enums\Action;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionRemover
{
    public function __construct(
        private ProcessTransactionSideEffects $processTransactionSideEffects,
        private \App\Services\Accounts\ReopenVirtualCustodians $reopenVirtualCustodians,
    ) {
    }

    public function execute(Transaction $transaction): array
    {
        $transaction->setRelation('account', $transaction->account()->withoutGlobalScopes()->first());
        DB::transaction(function () use ($transaction): void {
            \FinSi\FinanceDomain\Models\Account::query()->whereKey($transaction->account_id)->lockForUpdate()->firstOrFail();
            $subTransactions = Transaction::withoutGlobalScopes()
                ->where('parent_transaction_id', $transaction->id)
                ->orderBy('id')
                ->get();

            $subTransactions->each->delete();
            $transaction->allocations()->delete();
            $transaction->ledgerEntries()->delete();

            $transaction->delete();
            $this->reopenVirtualCustodians->execute($transaction->account);
        });

        $this->processTransactionSideEffects->execute($transaction, Action::Deleted);

        return [];
    }
}
