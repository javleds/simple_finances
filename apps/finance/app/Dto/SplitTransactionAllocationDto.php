<?php

namespace App\Dto;

readonly class SplitTransactionAllocationDto
{
    public function __construct(
        public int $userId,
        public float $percentage,
        public float $amount,
    ) {}
}
