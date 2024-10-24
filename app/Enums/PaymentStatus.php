<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor
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

    public function getColor(): array|string|null
    {
        return match ($this) {
            self::Pending => Color::Purple,
            self::Paid => Color::Teal,
        };
    }
}
