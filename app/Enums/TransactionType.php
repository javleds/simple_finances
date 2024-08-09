<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel, HasColor
{
    use EnumToArray;

    case Income = 'income';
    case Outcome = 'outcome';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Income => 'Ingreso',
            self::Outcome => 'Egreso',
        };
    }

    public function getColor(): array|string|null
    {
        return match ($this) {
            self::Income => Color::Green,
            self::Outcome => Color::Amber,
        };
    }
}
