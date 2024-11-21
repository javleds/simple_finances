<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FinancialGoalStatus: string implements HasLabel, HasColor
{
    use EnumToArray;

    case InProgress = 'in progress';
    case Completed = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::InProgress => 'En progreso',
            self::Completed => 'Completada',
        };
    }

    public function getColor(): array|string|null
    {
        return match ($this) {
            self::InProgress => Color::Purple,
            self::Completed => Color::Teal,
        };
    }

    public function getActionLabel(): ?string
    {
        return match ($this) {
            self::InProgress => 'hecho caso omiso de',
            self::Completed => 'aceptado',
        };
    }
}
