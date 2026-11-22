<?php

declare(strict_types=1);

namespace App\Dto;

class AccountBalanceMetaDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $balance,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'balance' => $this->balance,
        ];
    }
}
