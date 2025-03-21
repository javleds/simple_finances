<?php

namespace App\Listeners;

use App\Enums\FinancialGoalStatus;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\InteractsWithQueue;

class UpdateFinancialGoalsOnTransactionSaved
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TransactionSaved $event): void
    {
        $goals = $event->transaction->account->financialGoals()->where('user_id', auth()->id())->get();

        foreach ($goals as $goal) {
            $savedAmount = Transaction::income()
                ->where('financial_goal_id', $goal->id)
                ->where('user_id', auth()->id())
                ->sum('amount');

            $goal->progress = min(intval(($savedAmount * 100) / $goal->amount), 100);
            $goal->save();
        }
    }
}
