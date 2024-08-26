<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToSharedUsersScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder
                ->whereHas(
                    'users',
                    fn (Builder $query) => $query->where('user_id', auth()->id())
                );
        }
    }
}
