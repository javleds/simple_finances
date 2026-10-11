<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalMoneySetting extends Model
{
    protected function casts(): array
    {
        return [
            'user_id'               => 'integer',
            'account_id'            => 'integer',
            'activated_at'          => 'immutable_datetime',
            'transaction_cutoff_id' => 'integer',
            'ledger_cutoff_id'      => 'integer',
            'started_at'            => 'immutable_date',
            'opening_balance'       => 'float',
            'enabled'               => 'boolean',
        ];
    }
}
