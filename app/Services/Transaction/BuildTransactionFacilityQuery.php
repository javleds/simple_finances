<?php

namespace App\Services\Transaction;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BuildTransactionFacilityQuery
{
    public function __construct(private readonly OwnedTransactionsForUser $ownedTransactionsForUser) {}

    public function execute(array $filters, int $userId): Builder
    {
        $query = $this->ownedTransactionsForUser
            ->query($userId)
            ->with(['account', 'user', 'financialGoal', 'subTransactions'])
            ->whereNull('parent_transaction_id')
            ->where('status', TransactionStatus::Completed);

        $this->applyDateRange($query, $filters);
        $this->applySearch($query, $filters);

        return $query
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function applyDateRange(Builder $query, array $filters): void
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if (is_string($startDate) && $startDate !== '') {
            $query->where('scheduled_at', '>=', Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay());
        }

        if (is_string($endDate) && $endDate !== '') {
            $query->where('scheduled_at', '<=', Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay());
        }
    }

    private function applySearch(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search): void {
            $query->where('concept', 'like', '%'.$search.'%');
        });
    }
}
