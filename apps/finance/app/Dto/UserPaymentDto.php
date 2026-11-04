<?php

namespace App\Dto;

class UserPaymentDto
{
    public function __construct(
        public int $userId,
        public float $percentage,
    ) {}

    public static function fromFormArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            percentage: (float) $data['percentage'],
        );
    }
}
