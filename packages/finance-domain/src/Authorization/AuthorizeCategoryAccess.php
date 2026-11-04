<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use function abort_unless;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Category;
use FinSi\FinanceDomain\Queries\VisibleCategoriesForUser;

class AuthorizeCategoryAccess
{
    public function __construct(private readonly VisibleCategoriesForUser $categories)
    {
    }

    public function ensureVisible(Category $category, int $userId, ?Account $account = null): void
    {
        abort_unless($this->categories->query($userId, $account)->whereKey($category->id)->exists(), 404);
    }

    public function canManage(int $userId, ?Account $account = null): bool
    {
        return ! $account?->uses_shared_categories || $account->user_id === $userId;
    }
}
