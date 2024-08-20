<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Frequency: string implements HasLabel, HasColor
{
    use EnumToArray;

    case Day = 'days';
    case Month = 'months';
    case Year = 'years';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Day => 'Día / Días',
            self::Month => 'Mes / Meses',
            self::Year => 'Año / Años',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Day => Color::Teal,
            self::Month => Color::Pink,
            self::Year => Color::Purple,
        };
    }
}
