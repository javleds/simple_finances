<?php

namespace App\Enums;

enum Action
{
    case Created;
    case Updated;
    case Deleted;

    public function getLabel(): string
    {
        return match($this) {
          self::Created => 'creado',
          self::Updated => 'modificado',
          self::Deleted => 'eliminado',
        };
    }
}
