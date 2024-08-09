<?php

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel
{
    use EnumToArray;

    case Income = 'income';
    case Outcome = 'outcome';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Income => 'Ingrso',
            self::Outcome => 'Egreso',
        };
    }
}
