<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Dto\TransactionSummaryDto;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Eloquent\Builder;

class BuildTransactionSummary
{
    public function execute(Builder $query): TransactionSummaryDto
    {
        $incomeTotal = Decimal::normalize((clone $query)->income()->sum('amount'));
        $outcomeTotal = Decimal::normalize((clone $query)->outcome()->sum('amount'));

        return new TransactionSummaryDto(
            incomeTotal: $incomeTotal,
            outcomeTotal: $outcomeTotal,
            balance: Decimal::subtract($incomeTotal, $outcomeTotal),
        );
    }
}
