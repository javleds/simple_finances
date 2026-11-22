<?php

declare(strict_types=1);

namespace App\Services\FinancialGoals;

use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\FinancialGoal;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;

class RecalculateFinancialGoalProgress
{
    public function execute(FinancialGoal $financialGoal): FinancialGoal
    {
        $achievedAmount = Transaction::withoutGlobalScopes()
            ->where('financial_goal_id', $financialGoal->id)
            ->where('type', TransactionType::Income)
            ->where('status', TransactionStatus::Completed)
            ->sum('amount');

        $financialGoal->achieved_amount = Decimal::normalize((string) $achievedAmount);
        $financialGoal->progress = $this->calculateProgress(
            amount: $financialGoal->amount,
            achievedAmount: $financialGoal->achieved_amount,
        );
        $financialGoal->save();

        return $financialGoal;
    }

    private function calculateProgress(string $amount, string $achievedAmount): float
    {
        if (Decimal::compare($amount, '0.00') <= 0) {
            return 0.0;
        }

        $progress = Decimal::ratio($achievedAmount, 100, $amount, 8);

        return Decimal::compare($progress, '100.00') > 0 ? 100.0 : (float) $progress;
    }
}
