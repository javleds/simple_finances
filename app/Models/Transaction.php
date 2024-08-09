<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'account_id' => 'integer',
            'type' => TransactionType::class,
            'amount' => 'float',
            'scheduled_at' => 'immutable_datetime',
        ];
    }
}
