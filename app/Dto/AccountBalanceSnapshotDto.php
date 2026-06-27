<?php

namespace App\Dto;

class AccountBalanceSnapshotDto
{
    public function __construct(
        public readonly float $balance,
        public readonly ?float $spent = null,
        public readonly ?float $availableCredit = null,
    ) {}
}
