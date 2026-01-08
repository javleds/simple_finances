<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FixedIncomeFrequency: string implements HasColor, HasLabel
{
    use EnumToArray;

    case Monthly = 'monthly';
    case SemiMonthly = 'semi_monthly';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::SemiMonthly => 'Quincenal',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Monthly => Color::Pink,
            self::SemiMonthly => Color::Teal,
        };
    }
}
