<?php

namespace App\Traits;

use App\Models\Scopes\BelongsToSharedUsersScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToSharedUsers
{
    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToSharedUsersScope);

        static::creating(function (Model $model) {
            if (auth()->check()) {
                $model->users()->attach(auth()->id());
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
