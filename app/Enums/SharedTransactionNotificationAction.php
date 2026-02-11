<?php

namespace App\Enums;

enum SharedTransactionNotificationAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public static function fromAction(Action $action): self
    {
        return match ($action) {
            Action::Created => self::Created,
            Action::Updated => self::Updated,
            Action::Deleted => self::Deleted,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'creado',
            self::Updated => 'modificado',
            self::Deleted => 'eliminado',
        };
    }
}
