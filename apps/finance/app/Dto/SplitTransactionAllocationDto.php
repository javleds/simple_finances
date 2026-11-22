<?php

declare(strict_types=1);

namespace App\Dto;

readonly class SplitTransactionAllocationDto
{
    public function __construct(
        public int $userId,
        public string $percentage,
        public string $amount,
    ) {
    }
}
