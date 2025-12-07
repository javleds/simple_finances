<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionCreator
{
    public function __construct(
        private Guard $auth,
        private Dispatcher $dispatcher,
    ) {}

    public function execute(TransactionFormDto $dto): Transaction
    {
        if ($dto->type === TransactionType::Income && $dto->status !== TransactionStatus::Completed) {
            throw new \InvalidArgumentException('Income transactions must have status Completed.');
        }

        if ($dto->type !== TransactionType::Outcome || count($dto->userPayments) === 0) {
            return $this->createSingleTransaction($dto);
        }

        $transaction = DB::transaction(fn () => $this->createSplitTransactions($dto));

        $this->dispatcher->dispatch(new TransactionSaved($transaction, Action::Created));

        return $transaction;
    }

    private function createSingleTransaction(TransactionFormDto $dto): Transaction
    {
        $transaction = new Transaction();
        $transaction->type = $dto->type;
        $transaction->status = $dto->status;
        $transaction->concept = $dto->concept;
        $transaction->amount = $dto->amount;
        $transaction->account_id = $dto->accountId;
        $transaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
        $transaction->financial_goal_id = $dto->finanialGoalId ?: null;
        $transaction->user_id = $this->auth->id();
        $transaction->save();

        return $transaction;
    }

    private function createSplitTransactions(TransactionFormDto $dto): Transaction
    {
        $mainTransaction = new Transaction();
        $mainTransaction->type = $dto->type;
        $mainTransaction->status = TransactionStatus::Completed;
        $mainTransaction->concept = $dto->concept;
        $mainTransaction->amount = $dto->amount;
        $mainTransaction->account_id = $dto->accountId;
        $mainTransaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
        $mainTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
        $mainTransaction->user_id = $this->auth->id();
        $mainTransaction->save();

        foreach ($dto->userPayments as $paymentData) {
            $user = User::withoutGlobalScopes()->find($paymentData->userId);
            $amount = round($dto->amount * ($paymentData->percentage / 100), 2);

            $subTransaction = new Transaction();
            $subTransaction->type = TransactionType::Income;
            $subTransaction->status = TransactionStatus::Pending;
            $subTransaction->concept = $dto->concept . ' - Parte de ' . $user->name;
            $subTransaction->amount = $amount;
            $subTransaction->account_id = $dto->accountId;
            $subTransaction->scheduled_at = $this->resolveScheduleDate($dto->scheduledAt);
            $subTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $subTransaction->user_id = $user->id;
            $subTransaction->save();
        }

        return $mainTransaction;
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
