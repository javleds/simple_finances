<?php

declare(strict_types=1);

namespace App\Dto;

class TransactionSummaryDto
{
    public function __construct(
        public readonly string $incomeTotal,
        public readonly string $outcomeTotal,
        public readonly string $balance,
    ) {
    }

    public function toArray(): array
    {
        return [
            'income_total'  => $this->incomeTotal,
            'outcome_total' => $this->outcomeTotal,
            'balance'       => $this->balance,
        ];
    }
}
