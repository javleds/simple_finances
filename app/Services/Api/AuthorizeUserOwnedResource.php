<?php

namespace App\Services\Api;

use Illuminate\Database\Eloquent\Model;

class AuthorizeUserOwnedResource
{
    public function ensureOwned(Model $model, ?int $userId = null): void
    {
        abort_unless((int) $model->getAttribute('user_id') === $this->resolveUserId($userId), 403);
    }

    private function resolveUserId(?int $userId): int
    {
        return $userId ?? auth()->id();
    }
}
