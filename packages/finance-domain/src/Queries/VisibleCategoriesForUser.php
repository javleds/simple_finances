<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Queries;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class VisibleCategoriesForUser
{
    public function __construct(private readonly AuthorizeAccountAccess $accounts)
    {
    }

    public function query(int $userId, ?Account $account = null): Builder
    {
        if (null !== $account) {
            $this->accounts->ensureMember($account, $userId);
        }

        return $this->catalog($userId, $account);
    }

    private function catalog(int $userId, ?Account $account = null): Builder
    {
        if ($account?->uses_shared_categories) {
            return Category::query()->where('account_id', $account->id);
        }

        return Category::query()->whereNull('account_id')->where('user_id', $userId);
    }
}
