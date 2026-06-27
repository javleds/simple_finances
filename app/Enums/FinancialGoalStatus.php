<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum FinancialGoalStatus: string
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

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'purple',
            self::Completed => 'teal',
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
