<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum PaymentStatus: string
{
    use EnumToArray;

    case Pending = 'pending';
    case Paid = 'paid';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'purple',
            self::Paid => 'teal',
        };
    }
}
