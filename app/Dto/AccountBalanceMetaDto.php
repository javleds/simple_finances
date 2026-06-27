<?php

namespace App\Dto;

class AccountBalanceMetaDto
{
    public function __construct(
        public readonly int $id,
        public readonly float $balance,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'balance' => $this->balance,
        ];
    }
}
