<?php

namespace App\Listeners;

use App\Events\TransactionSaved;
use App\Services\FinancialGoals\RecalculateFinancialGoalProgress;

class UpdateFinancialGoalsOnTransactionSaved
{
    public function __construct(
        private readonly RecalculateFinancialGoalProgress $recalculateFinancialGoalProgress,
    ) {}

    public function handle(TransactionSaved $event): void
    {
        $account = $event->transaction->account()->withoutGlobalScopes()->first();

        if ($account === null) {
            return;
        }

        foreach ($account->financialGoals()->withoutGlobalScopes()->get() as $goal) {
            $this->recalculateFinancialGoalProgress->execute($goal);
        }
    }
}
