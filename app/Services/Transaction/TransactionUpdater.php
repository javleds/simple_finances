<?php

namespace App\Services\Transaction;

use App\Dto\SplitTransactionAllocationDto;
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
        private BuildSplitTransactionAllocations $buildSplitTransactionAllocations,
    ) {}

    public function execute(Transaction $transaction, TransactionFormDto $dto): Transaction
    {
        if ($dto->type === TransactionType::Income && $dto->status !== TransactionStatus::Completed) {
            throw new \InvalidArgumentException('Income transactions must have status Completed.');
        }

        $originalType = $transaction->type;

        $transaction = DB::transaction(function () use ($transaction, $dto, $originalType) {
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

            $this->syncSubTransactions($transaction, $subTransactions, $dto);

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
        $transaction->financial_goal_id = $dto->financialGoalId ?: null;
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

    private function syncSubTransactions(
        Transaction $transaction,
        Collection $subTransactions,
        TransactionFormDto $dto,
    ): void {
        $subTransactions->load('user');
        $allocations = collect($this->allocations($dto))
            ->keyBy(fn (SplitTransactionAllocationDto $allocation): int => $allocation->userId);

        foreach ($subTransactions as $subTransaction) {
            $allocation = $allocations->pull($subTransaction->user_id);

            if (! $allocation instanceof SplitTransactionAllocationDto) {
                $this->removeSubTransaction($subTransaction);

                continue;
            }

            $subTransaction->concept = $dto->concept.' - Parte de '.$subTransaction->user->name;
            $subTransaction->amount = $allocation->amount;
            $subTransaction->percentage = $allocation->percentage;
            $subTransaction->account_id = $dto->accountId;
            $subTransaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
            $subTransaction->financial_goal_id = $dto->financialGoalId ?: null;
            $subTransaction->save();
        }

        foreach ($allocations as $allocation) {
            $this->createSubTransaction($transaction, $dto, $allocation);
        }
    }

    private function createSubTransactions(Transaction $transaction, TransactionFormDto $dto): void
    {
        foreach ($this->allocations($dto) as $allocation) {
            $this->createSubTransaction($transaction, $dto, $allocation);
        }
    }

    private function allocations(TransactionFormDto $dto): array
    {
        return $this->buildSplitTransactionAllocations->execute($dto->amount, $dto->userPayments);
    }

    private function createSubTransaction(
        Transaction $transaction,
        TransactionFormDto $dto,
        SplitTransactionAllocationDto $allocation,
    ): void {
        $user = User::withoutGlobalScopes()->find($allocation->userId);

        if (! $user) {
            return;
        }

        $subTransaction = new Transaction;
        $subTransaction->type = TransactionType::Income;
        $subTransaction->status = TransactionStatus::Pending;
        $subTransaction->concept = $dto->concept.' - Parte de '.$user->name;
        $subTransaction->amount = $allocation->amount;
        $subTransaction->percentage = $allocation->percentage;
        $subTransaction->account_id = $dto->accountId;
        $subTransaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
        $subTransaction->financial_goal_id = $dto->financialGoalId ?: null;
        $subTransaction->user_id = $user->id;
        $subTransaction->parent_transaction_id = $transaction->id;
        $subTransaction->save();
    }

    private function removeSubTransaction(Transaction $subTransaction): void
    {
        if ($subTransaction->status === TransactionStatus::Pending) {
            $subTransaction->delete();

            return;
        }

        $subTransaction->parent_transaction_id = null;
        $subTransaction->save();
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
