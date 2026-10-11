<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use Illuminate\Database\Eloquent\Builder;

class ApplyTransactionCategoryAttachmentFilters
{
    public function execute(Builder $query, array $filters): void
    {
        $categoryIds = array_map('intval', $filters['category_ids'] ?? []);
        $uncategorized = (bool) ($filters['uncategorized'] ?? false);
        if ($categoryIds !== [] || $uncategorized) {
            $query->where(function (Builder $categories) use ($categoryIds, $uncategorized): void {
                if ($categoryIds !== []) {
                    $categories->whereIn('category_id', $categoryIds);
                }
                if ($uncategorized) {
                    $categories->orWhereNull('category_id');
                }
            });
        }
        if (array_key_exists('has_attachments', $filters)) {
            if ((bool) $filters['has_attachments']) {
                $query->whereHas('attachments');

                return;
            }
            $query->whereDoesntHave('attachments');
        }
    }
}
