<?php

namespace App\Services\Transaction;

use App\Dto\TransactionSummaryDto;
use Illuminate\Database\Eloquent\Builder;

class BuildTransactionSummary
{
    public function execute(Builder $query): TransactionSummaryDto
    {
        $incomeTotal = round((float) (clone $query)->income()->sum('amount'), 2);
        $outcomeTotal = round((float) (clone $query)->outcome()->sum('amount'), 2);

        return new TransactionSummaryDto(
            incomeTotal: $incomeTotal,
            outcomeTotal: $outcomeTotal,
            balance: round($incomeTotal - $outcomeTotal, 2),
        );
    }
}
