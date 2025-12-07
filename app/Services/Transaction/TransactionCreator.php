<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Models\Transaction;
use App\Models\User;
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

        if ($dto->type === TransactionType::Income || $dto->userPayments === []) {
            $transaction = new Transaction();
            $transaction->type = $dto->type;
            $transaction->status = $dto->status;
            $transaction->concept = $dto->concept;
            $transaction->amount = $dto->amount;
            $transaction->account_id = $dto->accountId;
            $transaction->scheduled_at = $dto->scheduledAt ?? Carbon::now();
            $transaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $transaction->user_id = $this->auth->id();
            $transaction->save();

            return $transaction;
        }

        $transaction = DB::transaction(function () use ($dto) {
            $mainTransaction = new Transaction();
            $mainTransaction->type = $dto->type;
            $mainTransaction->status = TransactionStatus::Completed;
            $mainTransaction->concept = $dto->concept;
            $mainTransaction->amount = $dto->amount;
            $mainTransaction->account_id = $dto->accountId;
            $mainTransaction->scheduled_at = $dto->scheduledAt ?? Carbon::now();
            $mainTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $mainTransaction->user_id = $this->auth->id();
            $mainTransaction->save();

            /** @var UserPaymentDto $paymentData */
            foreach ($dto->userPayments as $paymentData) {
                $user = User::withoutGlobalScopes()->find($paymentData->userId);
                $amount = (($paymentData->percentage * 100.0) / $dto->amount).round(2);

                $subTransaction = new Transaction();
                $subTransaction->type = TransactionType::Income;
                $subTransaction->status = TransactionStatus::Pending;
                $subTransaction->concept = $dto->concept . ' - Parte de ' . $user->name;
                $subTransaction->amount = $amount;
                $subTransaction->account_id = $dto->accountId;
                $subTransaction->scheduled_at = $dto->scheduledAt ?? Carbon::now();
                $subTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
                $subTransaction->user_id = $user->id;
                $subTransaction->save();
            }

            return $mainTransaction;
        });

        $this->dispatcher->dispatch(new TransactionSaved($transaction, Action::Created));

        return $transaction;
    }
}
