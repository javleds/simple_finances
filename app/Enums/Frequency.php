<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum Frequency: string
{
    use EnumToArray;

    case Day = 'days';
    case Month = 'months';
    case Year = 'years';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Day => 'Día / Días',
            self::Month => 'Mes / Meses',
            self::Year => 'Año / Años',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Day => 'teal',
            self::Month => 'pink',
            self::Year => 'purple',
        };
    }
}
