<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToSharedUsers
{
    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (auth()->check()) {
                $model->users()->syncWithoutDetaching([auth()->id()]);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
