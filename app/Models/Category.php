<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getScopeAttribute(): string
    {
        return null === $this->account_id ? 'personal' : 'shared';
    }

    protected $hidden = ['normalized_name'];

    protected $appends = ['scope'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'account_id' => 'integer', 'created_by_user_id' => 'integer'];
    }
}
