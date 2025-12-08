<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionUpdater
{
    public function __construct(
        private Guard $auth,
        private Dispatcher $dispatcher,
    ) {}

    public function execute(Transaction $transaction, TransactionFormDto $dto): Transaction
    {
        if ($dto->type === TransactionType::Income && $dto->status !== TransactionStatus::Completed) {
            throw new \InvalidArgumentException('Income transactions must have status Completed.');
        }

        $originalType = $transaction->type;
        $originalAmount = $transaction->amount;

        $transaction = DB::transaction(function () use ($transaction, $dto, $originalType, $originalAmount) {
            $this->applyBaseData($transaction, $dto);
            $transaction->save();

            if ($originalType !== $dto->type) {
                $this->handleTypeChange($transaction);
                return $transaction;
            }

            if ($dto->type !== TransactionType::Outcome && $transaction->subTransactions()->exists()) {
                $transaction->subTransactions()->where('status', TransactionStatus::Pending)->delete();
                $transaction->subTransactions()->where('status', TransactionStatus::Completed)->update(['parent_transaction_id' => null]);

                $this->dispatcher->dispatch(new TransactionSaved($transaction, Action::Updated));

                return $transaction;
            }

            $subTransactions = $transaction->subTransactions()->get();

            if ($dto->type === TransactionType::Outcome && $dto->userPayments !== [] && $subTransactions->isEmpty()) {
                $this->createSubTransactions($transaction, $dto);

                $this->dispatcher->dispatch(new TransactionSaved($transaction, Action::Updated));

                return $transaction;
            }

            if ($subTransactions->isEmpty()) {
                return $transaction;
            }

            if ($originalAmount === $dto->amount) {
                return $transaction;
            }

            $this->rebalanceSubTransactions(
                $subTransactions,
                $dto
            );

            return $transaction;
        });

        $this->dispatcher->dispatch(new TransactionSaved($transaction, Action::Updated));

        return $transaction;
    }

    private function applyBaseData(Transaction $transaction, TransactionFormDto $dto): void
    {
        $transaction->type = $dto->type;
        $transaction->status = $dto->status;
        $transaction->concept = $dto->concept;
        $transaction->amount = $dto->amount;
        $transaction->percentage = $dto->userPayments === [] ? 100.0 : $transaction->percentage;
        $transaction->account_id = $dto->accountId;
        $transaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
        $transaction->financial_goal_id = $dto->finanialGoalId ?: null;
        $transaction->user_id = $transaction->user_id ?? $this->auth->id();
    }

    private function handleTypeChange(Transaction $transaction): void
    {
        $pendingSubTransactions = $transaction->subTransactions()->pending()->get();
        $completedSubTransactions = $transaction->subTransactions()->completed()->get();

        foreach ($pendingSubTransactions as $subTransaction) {
            $subTransaction->delete();
        }

        foreach ($completedSubTransactions as $subTransaction) {
            $subTransaction->parent_transaction_id = null;
            $subTransaction->save();
        }
    }

    private function rebalanceSubTransactions(
        Collection $subTransactions,
        TransactionFormDto $dto,
    ): void {
        $subTransactions->load('user');

        foreach ($subTransactions as $subTransaction) {
            $percentage = collect($dto->userPayments)
                ->firstWhere('userId', $subTransaction->user_id)?->percentage ?? $subTransaction->percentage ?? 0.0;
            $amount = round($dto->amount * ($percentage / 100), 2);

            $subTransaction->amount = $amount;
            $subTransaction->percentage = $percentage;
            $subTransaction->account_id = $dto->accountId;
            $subTransaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
            $subTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $subTransaction->save();
        }
    }

    private function createSubTransactions(Transaction $transaction, TransactionFormDto $dto): void
    {
        foreach ($dto->userPayments as $paymentData) {
            $user = User::withoutGlobalScopes()->find($paymentData->userId);

            if (!$user) {
                continue;
            }

            $amount = round($dto->amount * ($paymentData->percentage / 100), 2);

            $subTransaction = new Transaction();
            $subTransaction->type = TransactionType::Income;
            $subTransaction->status = TransactionStatus::Pending;
            $subTransaction->concept = $dto->concept . ' - Parte de ' . $user->name;
            $subTransaction->amount = $amount;
            $subTransaction->percentage = $paymentData->percentage;
            $subTransaction->account_id = $dto->accountId;
            $subTransaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
            $subTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $subTransaction->user_id = $user->id;
            $subTransaction->parent_transaction_id = $transaction->id;

            $subTransaction->save();
        }
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
}
