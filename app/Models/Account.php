<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'balance' => 'float',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function updateBalance(): float
    {
        $this->balance = Transaction::income()->sum('amount') - Transaction::outcome()->sum('amount');
        $this->save();

        return $this->balance;
    }
}
