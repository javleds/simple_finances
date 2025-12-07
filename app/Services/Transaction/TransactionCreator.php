<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Auth\Guard;

class TransactionCreator
{
    public function __construct(
        private Guard $auth
    ) {}

    public function execute(TransactionFormDto $dto): Transaction
    {
        if ($dto->type === TransactionType::Income && $dto->status !== TransactionStatus::Completed) {
            throw new \InvalidArgumentException('Income transactions must have status Completed.');
        }

        if ($dto->type === TransactionType::Income || $dto->userPayments !== []) {
            $transaction = new Transaction();
            $transaction->type = $dto->type;
            $transaction->status = $dto->status;
            $transaction->concept = $dto->concept;
            $transaction->amount = $dto->amount;
            $transaction->account_id = $dto->accountId;
            $transaction->scheduled_at = $dto->scheduledAt ?: null;
            $transaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $transaction->user_id = $this->auth->id();
            $transaction->save();

            return $transaction;
        }

        $mainTransaction = new Transaction();
        $mainTransaction->type = $dto->type;
        $mainTransaction->status = TransactionStatus::Completed;
        $mainTransaction->concept = $dto->concept;
        $mainTransaction->amount = $dto->amount;
        $mainTransaction->account_id = $dto->accountId;
        $mainTransaction->scheduled_at = $dto->scheduledAt ?: null;
        $mainTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
        $mainTransaction->user_id = $this->auth->id();
        $mainTransaction->save();


        foreach ($dto->userPayments as $paymentData) {
            $user = User::withoutGlobalScopes()->find($paymentData['user_id']);
            $amount = (($paymentData['percentage'] * 100) / $paymentData['amount']).round(2);

            $subTransaction = new Transaction();
            $subTransaction->type = TransactionType::Income;
            $subTransaction->status = TransactionStatus::Pending;
            $subTransaction->concept = $dto->concept . ' - Parte de ' . $user->name;
            $subTransaction->amount = $amount;
            $subTransaction->account_id = $dto->accountId;
            $subTransaction->scheduled_at = $dto->scheduledAt ?: null;
            $subTransaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $subTransaction->user_id = $user->id;
            $subTransaction->save();
        }

        return $mainTransaction;
    }
}
