<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use function abort_unless;

use Illuminate\Database\Eloquent\Model;

class AuthorizeUserOwnedResource
{
    public function isOwned(Model $model, int $userId): bool
    {
        return (int) $model->getAttribute('user_id') === $userId;
    }

    public function ensureOwned(Model $model, int $userId): void
    {
        abort_unless($this->isOwned($model, $userId), 403);
    }
}
