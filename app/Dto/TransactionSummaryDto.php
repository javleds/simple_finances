<?php

namespace App\Dto;

class TransactionSummaryDto
{
    public function __construct(
        public readonly float $incomeTotal,
        public readonly float $outcomeTotal,
        public readonly float $balance,
    ) {}

    public function toArray(): array
    {
        return [
            'income_total' => $this->incomeTotal,
            'outcome_total' => $this->outcomeTotal,
            'balance' => $this->balance,
        ];
    }
}
