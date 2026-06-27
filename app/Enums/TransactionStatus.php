<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum TransactionStatus: string
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

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Completed => 'green',
        };
    }
}
