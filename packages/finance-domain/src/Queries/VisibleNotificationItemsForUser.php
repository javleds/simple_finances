<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Queries;

use FinSi\FinanceDomain\Models\SharedTransactionNotificationItem;
use Illuminate\Database\Eloquent\Builder;

class VisibleNotificationItemsForUser
{
    public function query(int $userId): Builder
    {
        return SharedTransactionNotificationItem::query()
            ->whereHas('batch', fn (Builder $query): Builder => $query->where('user_id', $userId));
    }
}
