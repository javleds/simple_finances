<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use function app;

use App\Dto\TransactionFormDto;
use App\Enums\Action;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\PersonalMoney\ApplyPersonalMoneyTransactionSource;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionCreator
{
    public function __construct(
        private Guard $auth,
        private SyncTransactionAllocations $syncTransactionAllocations,
        private SyncAccountMemberLedger $syncAccountMemberLedger,
        private ResolveOutcomeUserPayments $resolveOutcomeUserPayments,
        private SyncCoveredPayerRecoveryTransaction $syncCoveredPayerRecoveryTransaction,
        private ProcessTransactionSideEffects $processTransactionSideEffects,
        private ApplyPersonalMoneyTransactionSource $applyPersonalMoneyTransactionSource,
    ) {
    }

    public function execute(TransactionFormDto $dto): Transaction
    {
        if (TransactionStatus::Completed !== $dto->status) {
            throw new InvalidArgumentException('Transactions must have status Completed.');
        }

        $transaction = DB::transaction(function () use ($dto): Transaction {
            $transaction = $this->createSingleTransaction($dto);
            $this->syncTransactionAllocations->execute($transaction, $this->userPayments($transaction, $dto));
            $transaction->load('allocations');
            $this->syncAccountMemberLedger->execute($transaction);
            $transaction->load('paidByUser');
            $this->syncCoveredPayerRecoveryTransaction->execute($transaction);

            $this->applyPersonalMoneyTransactionSource->execute(
                $transaction, (int) $this->auth->id(), $dto->cashSource, true,
            );

            return $transaction;
        });

        $this->processTransactionSideEffects->execute($transaction, Action::Created);

        return $transaction;
    }

    private function createSingleTransaction(TransactionFormDto $dto): Transaction
    {
        $account = Account::withoutGlobalScopes()->whereKey($dto->accountId)->lockForUpdate()->firstOrFail();
        $members = $account->users()->withoutGlobalScopes()->pluck('users.id');
        $shared = $members->count() > 1;
        $personalUserId = (int) ($members->first() ?? $account->user_id);
        $transaction = new Transaction();
        $transaction->uses_shared_account_rules = $shared;
        $transaction->type = $dto->type;
        $transaction->status = $dto->status;
        $transaction->concept = $dto->concept;
        $transaction->amount = $dto->amount;
        $transaction->percentage = 100.0;
        $transaction->account_id = $dto->accountId;
        $transaction->paid_by_user_id = TransactionType::Outcome === $dto->type && ! $shared ? $personalUserId : $this->paidByUserId($dto);
        $transaction->custodian_user_id = TransactionType::Income === $dto->type && ! $shared ? $personalUserId : $this->custodianUserId($dto);
        $transaction->payment_source = TransactionType::Outcome === $dto->type && ! $shared ? TransactionPaymentSource::AccountFund : $this->paymentSource($dto);
        $transaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
        $transaction->financial_goal_id = $dto->financialGoalId ?: null;
        $transaction->user_id = $this->auth->id();
        $transaction->category_id = app(\App\Services\Categories\ResolveTransactionCategory::class)->execute($dto->accountId, $dto->categoryId, $this->auth->id(), $dto->type);
        $transaction->save();

        return $transaction;
    }

    private function userPayments(Transaction $transaction, TransactionFormDto $dto): array
    {
        if (false === $transaction->uses_shared_account_rules || TransactionType::Outcome !== $dto->type) {
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

    private function resolveScheduleDate(string | CarbonInterface $scheduledAt): CarbonInterface
    {
        if ($scheduledAt instanceof CarbonInterface) {
            return $scheduledAt;
        }

        if ('' === $scheduledAt) {
            return Carbon::now();
        }

        return Carbon::parse($scheduledAt);
    }

    private function paidByUserId(TransactionFormDto $dto): ?int
    {
        if (TransactionType::Outcome !== $dto->type) {
            return null;
        }

        return $dto->paidByUserId ?? $this->auth->id();
    }

    private function custodianUserId(TransactionFormDto $dto): ?int
    {
        if (TransactionType::Income !== $dto->type) {
            return null;
        }

        return $dto->custodianUserId ?? $this->auth->id();
    }

    private function paymentSource(TransactionFormDto $dto): ?TransactionPaymentSource
    {
        if (TransactionType::Outcome !== $dto->type) {
            return null;
        }

        return $dto->paymentSource;
    }
}
