<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Models\Transaction;
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

            if ($dto->type !== TransactionType::Outcome) {
                return $transaction;
            }

            $subTransactions = $transaction->subTransactions()->get();

            if ($subTransactions->isEmpty()) {
                return $transaction;
            }

            if ($originalAmount === $dto->amount) {
                return $transaction;
            }

            $this->rebalanceSubTransactions($subTransactions, $transaction, $dto->amount, $dto->concept, $dto->accountId, $this->resolveScheduleDate($dto->scheduledAt), $dto->finanialGoalId);

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
        Transaction $transaction,
        float $newAmount,
        string $concept,
        int $accountId,
        CarbonInterface $scheduledAt,
        ?int $financialGoalId,
    ): void {
        $totalSubAmount = $subTransactions->sum('amount');

        if ($totalSubAmount <= 0.0) {
            return;
        }

        $subTransactions->load('user');

        foreach ($subTransactions as $subTransaction) {
            $percentage = $subTransaction->amount / $totalSubAmount;
            $subTransaction->amount = round($newAmount * $percentage, 2);
            $subTransaction->concept = $concept . ' - Parte de ' . $subTransaction->user->name;
            $subTransaction->account_id = $accountId;
            $subTransaction->scheduled_at = $scheduledAt;
            $subTransaction->financial_goal_id = $financialGoalId ?: null;
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
