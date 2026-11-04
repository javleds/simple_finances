<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\ReadModels;

use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Queries\VisibleTransactionsForUser;
use Illuminate\Support\Carbon;

use function round;

class BuildDashboardPeriodSummary
{
    public function __construct(
        private readonly VisibleTransactionsForUser $visibleTransactionsForUser,
    ) {
    }

    public function execute(int $userId, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

        $incomeTotal = $this->sumByType($userId, TransactionType::Income, $start, $end);
        $outcomeTotal = $this->sumByType($userId, TransactionType::Outcome, $start, $end);

        return [
            'period'        => [
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
            ],
            'income_total'  => $incomeTotal,
            'outcome_total' => $outcomeTotal,
            'balance'       => round($incomeTotal - $outcomeTotal, 2),
        ];
    }

    private function sumByType(int $userId, TransactionType $type, Carbon $start, Carbon $end): float
    {
        $total = $this->visibleTransactionsForUser
            ->query($userId)
            ->completed()
            ->whereNull('parent_transaction_id')
            ->where('type', $type)
            ->whereBetween('scheduled_at', [$start, $end])
            ->sum('amount');

        return round((float) $total, 2);
    }
}
