<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use FinSi\FinanceDomain\Enums\Action;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TransactionRemover
{
    public function __construct(
        private ProcessTransactionSideEffects $processTransactionSideEffects,
        private \App\Services\Accounts\ReopenVirtualCustodians $reopenVirtualCustodians,
    ) {
    }

    public function execute(Transaction $transaction): array
    {
        $sourceAccountId = Transaction::withoutGlobalScopes()->whereKey($transaction->id)->firstOrFail(['id', 'account_id'])->account_id;
        if ($sourceAccountId !== $transaction->account_id) {
            throw new ConflictHttpException('The transaction moved to another account. Refresh it before retrying.');
        }
        DB::transaction(function () use ($transaction, $sourceAccountId): void {
            $account = Account::withoutGlobalScopes()->whereKey($sourceAccountId)->lockForUpdate()->firstOrFail();
            $transaction = Transaction::withoutGlobalScopes()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->account_id !== $sourceAccountId) {
                throw new ConflictHttpException('The transaction moved to another account. Refresh it before retrying.');
            }
            $transaction->setRelation('account', $account);
            $subTransactions = Transaction::withoutGlobalScopes()
                ->where('parent_transaction_id', $transaction->id)
                ->orderBy('id')
                ->get();

            $subTransactions->each->delete();
            $transaction->allocations()->delete();
            $transaction->ledgerEntries()->delete();

            $transaction->delete();
            $this->reopenVirtualCustodians->execute($transaction->account);
            $this->processTransactionSideEffects->execute($transaction, Action::Deleted);
        });

        return [];
    }
}
