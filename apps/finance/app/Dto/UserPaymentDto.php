<?php

declare(strict_types=1);

namespace App\Dto;

use FinSi\FinanceDomain\Money\Decimal;

class UserPaymentDto
{
    public function __construct(
        public int $userId,
        public string $percentage,
    ) {
        $this->percentage = Decimal::normalize($percentage);
    }

    public static function fromFormArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            percentage: Decimal::normalize($data['percentage']),
        );
    }
}
