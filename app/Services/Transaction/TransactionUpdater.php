<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\Action;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Accounts\RecalculateAccountBalance;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\DB;

class TransactionUpdater
{
    public function __construct(
        private Guard $auth,
        private SyncTransactionAllocations $syncTransactionAllocations,
        private SyncAccountMemberLedger $syncAccountMemberLedger,
        private ResolveOutcomeUserPayments $resolveOutcomeUserPayments,
        private SyncCoveredPayerRecoveryTransaction $syncCoveredPayerRecoveryTransaction,
        private ProcessTransactionSideEffects $processTransactionSideEffects,
        private RecalculateAccountBalance $recalculateAccountBalance,
    ) {
    }

    public function execute(Transaction $transaction, TransactionFormDto $dto): Transaction
    {
        if ($dto->status !== TransactionStatus::Completed) {
            throw new \InvalidArgumentException('Transactions must have status Completed.');
        }

        $previousAccountId = $transaction->account_id;
        $transaction = DB::transaction(function () use ($transaction, $dto, &$previousAccountId) {
            Account::withoutGlobalScopes()->whereIn('id', [$transaction->account_id, $dto->accountId])->orderBy('id')->lockForUpdate()->get();
            $persisted = Transaction::withoutGlobalScopes()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $transaction = $persisted;
            $previousAccountId = $persisted->account_id;
            $this->syncCoveredPayerRecoveryTransaction->deleteExisting($transaction);
            $transaction->ledgerEntries()->delete();
            $this->applyBaseData($transaction, $dto);
            $transaction->save();
            $this->syncTransactionAllocations->execute($transaction, $this->userPayments($transaction, $dto));
            $transaction->load('allocations');
            $this->syncAccountMemberLedger->execute($transaction);
            $transaction->load('paidByUser');
            $this->syncCoveredPayerRecoveryTransaction->execute($transaction);

            return $transaction;
        }, 3);

        if ($previousAccountId !== $transaction->account_id) {
            $previousAccount = Account::withoutGlobalScopes()->find($previousAccountId);
            if ($previousAccount !== null) {
                $this->recalculateAccountBalance->execute($previousAccount);
            }
        }
        $this->processTransactionSideEffects->execute($transaction, Action::Updated);

        return $transaction;
    }

    private function applyBaseData(Transaction $transaction, TransactionFormDto $dto): void
    {
        $transaction->type = $dto->type;
        $transaction->status = $dto->status;
        $transaction->concept = $dto->concept;
        $transaction->amount = $dto->amount;
        $transaction->percentage = 100.0;
        $categoryId = $dto->categoryProvided ? $dto->categoryId : $transaction->category_id;
        if (! $dto->categoryProvided && $transaction->account_id !== $dto->accountId) {
            $categoryId = null;
        }
        $transaction->category_id = app(\App\Services\Categories\ResolveTransactionCategory::class)->execute($dto->accountId, $categoryId, $transaction->user_id ?? $this->auth->id(), $dto->type);
        $sameAccount = $transaction->account_id === $dto->accountId;
        $previousPayerId = $transaction->paid_by_user_id;
        $previousCustodianId = $transaction->custodian_user_id;
        $account = Account::withoutGlobalScopes()->findOrFail($dto->accountId);
        $members = $account->users()->withoutGlobalScopes()->pluck('users.id');
        if (! $sameAccount) {
            $transaction->uses_shared_account_rules = $members->count() > 1;
        }
        $personalUserId = (int) ($members->first() ?? $account->user_id);
        $transaction->account_id = $dto->accountId;
        $transaction->paid_by_user_id = $this->paidByUserId($dto);
        $transaction->custodian_user_id = $this->custodianUserId($dto);
        $transaction->payment_source = $this->paymentSource($dto);
        if ($transaction->uses_shared_account_rules === false) {
            $transaction->paid_by_user_id = $dto->type === TransactionType::Outcome ? ($sameAccount ? ($previousPayerId ?? $personalUserId) : $personalUserId) : null;
            $transaction->custodian_user_id = $dto->type === TransactionType::Income ? ($sameAccount ? ($previousCustodianId ?? $personalUserId) : $personalUserId) : null;
            $transaction->payment_source = $dto->type === TransactionType::Outcome ? TransactionPaymentSource::AccountFund : null;
        }
        $transaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
        $transaction->financial_goal_id = $dto->financialGoalId ?: null;
        $transaction->user_id = $transaction->user_id ?? $this->auth->id();
    }

    private function userPayments(Transaction $transaction, TransactionFormDto $dto): array
    {
        if ($transaction->uses_shared_account_rules === false || $dto->type !== TransactionType::Outcome) {
            return [];
        }

        if (! $dto->splitBetweenUsers) {
            return [];
        }

        $account = Account::withoutGlobalScopes()->findOrFail($transaction->account_id);

        return $this->resolveOutcomeUserPayments->execute(
            account: $account,
            amount: $transaction->amount,
            paidByUserId: $transaction->paid_by_user_id ?? $transaction->user_id,
            paymentSource: $transaction->payment_source ?? TransactionPaymentSource::AccountFund,
            requestedUserPayments: $dto->userPayments,
        );
    }

    private function resolveScheduleDate(string|CarbonInterface $scheduledAt): CarbonInterface
    {
        if ($scheduledAt instanceof CarbonInterface) {
            return $scheduledAt;
        }

        if ($scheduledAt === '') {
            return Carbon::now();
        }

        return Carbon::parse($scheduledAt);
    }

    private function paidByUserId(TransactionFormDto $dto): ?int
    {
        if ($dto->type !== TransactionType::Outcome) {
            return null;
        }

        return $dto->paidByUserId ?? $this->auth->id();
    }

    private function custodianUserId(TransactionFormDto $dto): ?int
    {
        if ($dto->type !== TransactionType::Income) {
            return null;
        }

        return $dto->custodianUserId ?? $this->auth->id();
    }

    private function paymentSource(TransactionFormDto $dto): ?TransactionPaymentSource
    {
        if ($dto->type !== TransactionType::Outcome) {
            return null;
        }

        return $dto->paymentSource;
    }
}
