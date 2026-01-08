<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FixedOutcomeType: string implements HasColor, HasLabel
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

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Savings => Color::Teal,
            self::Transfer => Color::Pink,
        };
    }
}
