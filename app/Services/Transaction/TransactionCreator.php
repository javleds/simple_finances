<?php

namespace App\Services\Transaction;

use App\Dto\TransactionFormDto;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;

class TransactionCreator
{
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
            $transaction->save();

            return $transaction;
        }

        $transaction = new Transaction();
        $transaction->type = $dto->type;
        $transaction->status = TransactionStatus::Completed;
        $transaction->concept = $dto->concept;
        $transaction->amount = $dto->amount;
        $transaction->account_id = $dto->accountId;
        $transaction->scheduled_at = $dto->scheduledAt ?: null;
        $transaction->financial_goal_id = $dto->finanialGoalId ?: null;
        $transaction->save();


        foreach ($dto->userPayments as $paymentData) {
            $user = User::withoutGlobalScopes()->find($paymentData['user_id']);
            $amount = (($paymentData['percentage'] * 100) / $paymentData['amount']).round(2);

            $transaction = new Transaction();
            $transaction->type = TransactionType::Income;
            $transaction->status = TransactionStatus::Pending;
            $transaction->concept = $dto->concept . ' - Parte de ' . $user->name;
            $transaction->amount = $amount;
            $transaction->account_id = $dto->accountId;
            $transaction->scheduled_at = $dto->scheduledAt ?: null;
            $transaction->financial_goal_id = $dto->finanialGoalId ?: null;
            $transaction->user_id = $user->id;
            $transaction->save();
        }

        return $transaction;
    }
}
