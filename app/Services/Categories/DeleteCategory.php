<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function execute(Category $category, ?string $action, ?int $targetCategoryId): void
    {
        DB::transaction(static function () use ($category, $action, $targetCategoryId): void {
            $accountIds = Transaction::withoutGlobalScopes()->where('category_id', $category->id)->pluck('account_id');
            if (null !== $category->account_id) {
                $accountIds->push($category->account_id);
            }
            Account::withoutGlobalScopes()->whereIn('id', $accountIds->unique())->orderBy('id')->lockForUpdate()->get();
            $ids = array_filter([$category->id, $targetCategoryId]);
            $categories = Category::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $categories->get($category->id);
            abort_unless($source, 404);
            $transactions = Transaction::withoutGlobalScopes()->where('category_id', $source->id);

            if (null === $action && (clone $transactions)->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages(['action' => 'Elige qué hacer con los movimientos de esta categoría.']);
            }

            $target = $categories->get($targetCategoryId);
            if ('reassign' === $action && (! $target || $target->id === $source->id || $target->user_id !== $source->user_id || $target->account_id !== $source->account_id)) {
                throw ValidationException::withMessages(['target_category_id' => 'Selecciona otra categoría del mismo catálogo.']);
            }

            $transactions->update(['category_id' => 'reassign' === $action ? $target->id : null]);
            $source->delete();
        }, 3);
    }
}
