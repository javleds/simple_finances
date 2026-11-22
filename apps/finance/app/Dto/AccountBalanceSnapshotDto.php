<?php

declare(strict_types=1);

namespace App\Dto;

class AccountBalanceSnapshotDto
{
    public function __construct(
        public readonly string $balance,
        public readonly ?string $spent = null,
        public readonly ?string $availableCredit = null,
    ) {
    }
}
