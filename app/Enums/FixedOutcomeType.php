<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum FixedOutcomeType: string
{
    use EnumToArray;

    case Savings = 'savings';
    case Transfer = 'transfer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Savings => 'Ahorros',
            self::Transfer => 'Transferencia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Savings => 'teal',
            self::Transfer => 'pink',
        };
    }
}
