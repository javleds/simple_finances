<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Enums;

use FinSi\FinanceDomain\Traits\EnumToArray;

enum FixedIncomeFrequency: string
{
    use EnumToArray;

    case Monthly = 'monthly';
    case SemiMonthly = 'semi_monthly';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Monthly     => 'Mensual',
            self::SemiMonthly => 'Quincenal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Monthly     => 'pink',
            self::SemiMonthly => 'teal',
        };
    }
}
