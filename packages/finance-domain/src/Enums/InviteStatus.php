<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Enums;

use FinSi\FinanceDomain\Traits\EnumToArray;

enum InviteStatus: string
{
    use EnumToArray;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending  => 'Pendiente',
            self::Accepted => 'Aceptada',
            self::Declined => 'Declinada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending  => 'purple',
            self::Accepted => 'teal',
            self::Declined => 'red',
        };
    }

    public function getActionLabel(): ?string
    {
        return match ($this) {
            self::Pending  => 'hecho caso omiso de',
            self::Accepted => 'aceptado',
            self::Declined => 'declinado',
        };
    }
}
