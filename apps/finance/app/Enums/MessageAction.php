<?php

namespace App\Enums;

enum MessageAction: string
{
    case CreateTransaction = 'create_transaction';
    case QueryBalance = 'query_balance';
    case QueryRecentTransactions = 'query_recent_transactions';
    case ModifyLastTransaction = 'modify_last_transaction';
    case DeleteLastTransaction = 'delete_last_transaction';

    public function getLabel(): string
    {
        return match ($this) {
            self::CreateTransaction => 'Crear transacción',
            self::QueryBalance => 'Consultar balance',
            self::QueryRecentTransactions => 'Consultar movimientos recientes',
            self::ModifyLastTransaction => 'Modificar última transacción',
            self::DeleteLastTransaction => 'Eliminar última transacción',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CreateTransaction => 'El usuario quiere crear una nueva transacción',
            self::QueryBalance => 'El usuario quiere consultar el balance de una cuenta',
            self::QueryRecentTransactions => 'El usuario quiere ver los últimos movimientos de una cuenta',
            self::ModifyLastTransaction => 'El usuario quiere modificar su última transacción creada',
            self::DeleteLastTransaction => 'El usuario quiere eliminar su última transacción creada',
        };
    }
}
