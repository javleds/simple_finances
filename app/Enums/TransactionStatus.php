<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionStatus: string implements HasLabel, HasColor
{
    use EnumToArray;

    case Pending = 'pending';
    case Completed = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Completado',
        };
    }

    public function getColor(): array|string|null
    {
        return match ($this) {
            self::Pending => Color::Amber,
            self::Completed => Color::Green,
        };
    }
}
