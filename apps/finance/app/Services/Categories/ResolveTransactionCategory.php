<?php

declare(strict_types=1);

namespace App\Services\Categories;

use FinSi\FinanceDomain\Queries\VisibleCategoriesForUser;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Category;
use Illuminate\Validation\ValidationException;

class ResolveTransactionCategory
{
    public function __construct(private readonly VisibleCategoriesForUser $categories) {}

    public function execute(int $accountId, ?int $categoryId, int $userId, TransactionType $type): ?int
    {
        $account = Account::withoutGlobalScopes()->lockForUpdate()->findOrFail($accountId);

        if (null === $categoryId) {
            return null;
        }

        $query = $this->categories->query($userId, $account)
            ->whereKey($categoryId)->whereIn('transaction_type', [$type->value, 'both']);

        if (null === $query->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['category_id' => 'La categoría no está disponible para esta cuenta y tipo de movimiento.']);
        }

        return $categoryId;
    }
}
