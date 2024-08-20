<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'finished_at' => 'date',
            'started_at' => 'date',
            'amount' => 'float',
            'feed_account_id' => 'integer',
            'frequency_type' => Frequency::class,
            'frequency_unit' => 'integer',
        ];
    }

    public function feedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
