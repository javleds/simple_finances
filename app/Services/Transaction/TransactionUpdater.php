<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\Action;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
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
        private ProcessTransactionSideEffects $processTransactionSideEffects,
    ) {}

    public function execute(Transaction $transaction, TransactionFormDto $dto): Transaction
    {
        if ($dto->status !== TransactionStatus::Completed) {
            throw new \InvalidArgumentException('Transactions must have status Completed.');
        }

        $transaction = DB::transaction(function () use ($transaction, $dto) {
            $this->applyBaseData($transaction, $dto);
            $transaction->save();
            $this->syncTransactionAllocations->execute($transaction, $this->userPayments($transaction, $dto));
            $transaction->load('allocations');
            $this->syncAccountMemberLedger->execute($transaction);

            return $transaction;
        });

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
        $transaction->account_id = $dto->accountId;
        $transaction->paid_by_user_id = $this->paidByUserId($dto);
        $transaction->custodian_user_id = $this->custodianUserId($dto);
        $transaction->payment_source = $this->paymentSource($dto);
        $transaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
        $transaction->financial_goal_id = $dto->financialGoalId ?: null;
        $transaction->user_id = $transaction->user_id ?? $this->auth->id();
    }

    private function userPayments(Transaction $transaction, TransactionFormDto $dto): array
    {
        if ($dto->type !== TransactionType::Outcome) {
            return [];
        }

        if ($dto->userPayments !== []) {
            return $dto->userPayments;
        }

        return $this->syncTransactionAllocations->defaultOutcomeAllocation($transaction);
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
