<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

class ResolveTransactionCategory
{
    public function execute(int $accountId, ?int $categoryId, int $userId): ?int
    {
        $account = Account::withoutGlobalScopes()->lockForUpdate()->findOrFail($accountId);

        if (null === $categoryId) {
            return null;
        }

        $query = Category::query()->whereKey($categoryId);
        $account->uses_shared_categories
            ? $query->where('account_id', $account->id)
            : $query->whereNull('account_id')->where('user_id', $userId);

        if (null === $query->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['category_id' => 'La categoría no está disponible para esta cuenta.']);
        }

        return $categoryId;
    }
}
