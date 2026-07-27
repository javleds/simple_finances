<?php

namespace App\Services\Transaction;

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;

class SyncAccountMemberLedger
{
    public function execute(Transaction $transaction): void
    {
        $transaction->ledgerEntries()->delete();

        if ($transaction->status !== TransactionStatus::Completed || $transaction->legacy_migrated_at !== null) {
            return;
        }

        if ($transaction->type === TransactionType::Income) {
            $this->recordIncomeCustody($transaction);

            return;
        }

        $this->recordOutcome($transaction);
    }

    private function recordIncomeCustody(Transaction $transaction): void
    {
        $transaction->ledgerEntries()->create([
            'account_id' => $transaction->account_id,
            'user_id' => $transaction->custodian_user_id ?? $transaction->user_id,
            'type' => AccountMemberLedgerEntryType::IncomeCustody,
            'amount' => $transaction->amount,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
    }

    private function recordOutcome(Transaction $transaction): void
    {
        $source = $transaction->payment_source ?? TransactionPaymentSource::AccountFund;
        $paidByUserId = $transaction->paid_by_user_id ?? $transaction->user_id;

        if ($source === TransactionPaymentSource::AccountFund) {
            $allocations = $transaction->allocations->isNotEmpty()
                ? $transaction->allocations
                : collect([(object) ['user_id' => $paidByUserId, 'amount' => $transaction->amount]]);

            foreach ($allocations as $allocation) {
                $transaction->ledgerEntries()->create([
                    'account_id' => $transaction->account_id,
                    'user_id' => $allocation->user_id,
                    'type' => AccountMemberLedgerEntryType::AccountFundExpense,
                    'amount' => $allocation->amount * -1,
                    'description' => $transaction->concept,
                    'occurred_at' => $transaction->scheduled_at,
                ]);
            }

            return;
        }

        $transaction->ledgerEntries()->create([
            'account_id' => $transaction->account_id,
            'user_id' => $paidByUserId,
            'type' => AccountMemberLedgerEntryType::ExpensePaid,
            'amount' => $transaction->amount,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);

        foreach ($transaction->allocations as $allocation) {
            $transaction->ledgerEntries()->create([
                'account_id' => $transaction->account_id,
                'user_id' => $allocation->user_id,
                'related_user_id' => $paidByUserId,
                'type' => AccountMemberLedgerEntryType::ExpenseShare,
                'amount' => $allocation->amount * -1,
                'description' => $transaction->concept,
                'occurred_at' => $transaction->scheduled_at,
            ]);
        }
    }
}
