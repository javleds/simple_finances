<?php

namespace App\Services\Dashboard;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class BuildDashboardPeriodSummary
{
    public function execute(string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

        $incomeTotal = $this->sumByType(TransactionType::Income, $start, $end);
        $outcomeTotal = $this->sumByType(TransactionType::Outcome, $start, $end);

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'income_total' => $incomeTotal,
            'outcome_total' => $outcomeTotal,
            'balance' => round($incomeTotal - $outcomeTotal, 2),
        ];
    }

    private function sumByType(TransactionType $type, Carbon $start, Carbon $end): float
    {
        $total = Transaction::query()
            ->completed()
            ->whereNull('parent_transaction_id')
            ->where('type', $type)
            ->whereBetween('scheduled_at', [$start, $end])
            ->sum('amount');

        return round((float) $total, 2);
    }
}
