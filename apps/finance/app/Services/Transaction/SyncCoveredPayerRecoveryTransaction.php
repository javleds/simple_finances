<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Services\Accounts\BuildAccountCustodySummary;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Carbon;

class SyncCoveredPayerRecoveryTransaction
{
    public function __construct(private readonly BuildAccountCustodySummary $buildAccountCustodySummary)
    {
    }

    public function execute(Transaction $transaction): void
    {
        $this->deleteExisting($transaction);

        if (! $this->shouldCreateRecovery($transaction)) {
            return;
        }

        $payerShare = Decimal::sum($transaction->allocations
            ->where('user_id', $transaction->paid_by_user_id), 'amount');

        if (Decimal::compare($payerShare, 0) <= 0) {
            return;
        }

        $payerName = $transaction->paidByUser?->name ?? 'quien pago';

        $recovery = new Transaction();
        $recovery->uses_shared_account_rules = $transaction->uses_shared_account_rules;
        $recovery->parent_transaction_id = $transaction->id;
        $recovery->type = TransactionType::Income;
        $recovery->status = TransactionStatus::Completed;
        $recovery->concept = self::CONCEPT_PREFIX . $payerName . ': ' . $transaction->concept;
        $recovery->amount = $payerShare;
        $recovery->percentage = '100.00';
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
            ->where('concept', 'like', self::CONCEPT_PREFIX . '%')
            ->delete();
    }
    private const CONCEPT_PREFIX = 'Parte cubierta por ';

    private function shouldCreateRecovery(Transaction $transaction): bool
    {
        if (false === $transaction->uses_shared_account_rules || TransactionType::Outcome !== $transaction->type
            || TransactionStatus::Completed !== $transaction->status
            || TransactionPaymentSource::MemberOutOfPocket !== $transaction->payment_source
            || null === $transaction->paid_by_user_id
        ) {
            return false;
        }

        $account = Account::withoutGlobalScopes()->find($transaction->account_id);

        if (! $account) {
            return false;
        }

        if (Decimal::compare(Decimal::normalize($account->balance), '0.00') > 0) {
            return false;
        }

        return Decimal::compare($this->buildAccountCustodySummary->positiveTotal($account), '0.00') <= 0;
    }
}
