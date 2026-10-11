<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalMoneyReimbursement extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'user_id'          => 'integer',
            'account_id'       => 'integer',
            'ledger_entry_key' => 'integer',
            'cash_effect'      => 'float',
            'occurred_at'      => 'immutable_datetime',
        ];
    }
}
