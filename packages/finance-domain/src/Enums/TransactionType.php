<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Enums;

use FinSi\FinanceDomain\Traits\EnumToArray;

enum TransactionType: string
{
    use EnumToArray;

    case Income = 'income';
    case Outcome = 'outcome';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Income  => 'Ingreso',
            self::Outcome => 'Egreso',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Income  => 'green',
            self::Outcome => 'amber',
        };
    }
}
