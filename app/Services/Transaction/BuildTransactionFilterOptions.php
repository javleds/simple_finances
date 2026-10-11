<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class BuildTransactionFilterOptions
{
    public function execute(Builder $visibleTransactions): array
    {
        $categoryIds = (clone $visibleTransactions)->reorder()->select('transactions.category_id')->whereNotNull('transactions.category_id');

        return Category::query()->whereIn('categories.id', $categoryIds)
            ->leftJoin('accounts', 'accounts.id', '=', 'categories.account_id')
            ->orderBy('categories.name')->orderBy('categories.id')
            ->get(['categories.id', 'categories.name', 'accounts.name as account_name'])
            ->map(static fn (Category $category): array => ['id' => (int) $category->id, 'name' => $category->name, 'account_name' => $category->account_name])
            ->all();
    }
}
