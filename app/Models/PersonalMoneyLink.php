<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalMoneyLink extends Model
{
    protected function casts(): array
    {
        return [
            'user_id'           => 'integer',
            'transaction_id'    => 'integer',
            'reimbursement_id'  => 'integer',
            'source_account_id' => 'integer',
            'cash_effect'       => 'float',
            'occurred_at'       => 'immutable_datetime',
        ];
    }
}
