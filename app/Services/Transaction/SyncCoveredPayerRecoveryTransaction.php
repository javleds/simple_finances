<?php

namespace App\Services\Transaction;

use App\Models\Transaction;

class SyncCoveredPayerRecoveryTransaction
{
    private const CONCEPT_PREFIX = 'Parte cubierta por ';

    public function execute(Transaction $transaction): void
    {
        $this->deleteExisting($transaction);

        if (! $this->shouldCreateRecovery($transaction)) {
            return;
        }

        $payerShare = round((float) $transaction->allocations
            ->where('user_id', $transaction->paid_by_user_id)
            ->sum('amount'), 2);

        if ($payerShare <= 0.0) {
            return;
        }

        $payerName = $transaction->paidByUser?->name ?? 'quien pago';

        $recovery = new Transaction;
        $recovery->parent_transaction_id = $transaction->id;
        $recovery->type = TransactionType::Income;
        $recovery->status = TransactionStatus::Completed;
        $recovery->concept = self::CONCEPT_PREFIX.$payerName.': '.$transaction->concept;
        $recovery->amount = $payerShare;
        $recovery->percentage = 100.0;
        $recovery->account_id = $transaction->account_id;
        $recovery->user_id = $transaction->paid_by_user_id;
        $recovery->paid_by_user_id = null;
        $recovery->custodian_user_id = null;
        $recovery->payment_source = null;
        $recovery->scheduled_at = $transaction->scheduled_at;
        $recovery->financial_goal_id = null;
        $recovery->legacy_migrated_at = Carbon::now();
        $recovery->save();
    }

    public function deleteExisting(Transaction $transaction): void
    {
        $transaction->subTransactions()
            ->where('concept', 'like', self::CONCEPT_PREFIX.'%')
            ->delete();
    }

    private function shouldCreateRecovery(Transaction $transaction): bool
    {
        return false;
    }
}
