<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InviteStatus: string implements HasLabel, HasColor
{
    use EnumToArray;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Accepted => 'Aceptada',
            self::Declined => 'Declinada',
        };
    }

    public function getColor(): array|string|null
    {
        return match ($this) {
            self::Pending => Color::Purple,
            self::Accepted => Color::Teal,
            self::Declined => Color::Red,
        };
    }

    public function getActionLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'hecho caso omiso de',
            self::Accepted => 'aceptado',
            self::Declined => 'declinado',
        };
    }
}
