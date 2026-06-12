<?php

namespace App\Services\FinancialGoals;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\FinancialGoal;
use App\Models\Transaction;

class RecalculateFinancialGoalProgress
{
    public function execute(FinancialGoal $financialGoal): FinancialGoal
    {
        $achievedAmount = Transaction::withoutGlobalScopes()
            ->where('financial_goal_id', $financialGoal->id)
            ->where('type', TransactionType::Income)
            ->where('status', TransactionStatus::Completed)
            ->sum('amount');

        $financialGoal->achieved_amount = (float) $achievedAmount;
        $financialGoal->progress = $this->calculateProgress(
            amount: $financialGoal->amount,
            achievedAmount: $financialGoal->achieved_amount,
        );
        $financialGoal->save();

        return $financialGoal;
    }

    private function calculateProgress(float $amount, float $achievedAmount): float
    {
        if ($amount <= 0.0) {
            return 0.0;
        }

        return min(($achievedAmount * 100) / $amount, 100.0);
    }
}
