<?php

namespace App\Services\SharedTransactions;

use App\Enums\SharedTransactionNotificationAction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\SharedTransactionNotificationItem;
use App\Models\User;
use App\Services\Accounts\BuildAccountMemberSummary;
use Illuminate\Support\Collection;

class BuildSharedTransactionNotificationMailData
{
    public function __construct(private readonly BuildAccountMemberSummary $buildAccountMemberSummary) {}

    /**
     * @param Collection<int, SharedTransactionNotificationItem> $items
     * @return array<string, mixed>
     */
    public function execute(User $user, Collection $items): array
    {
        $itemsByAccount = $items
            ->filter(fn (SharedTransactionNotificationItem $item): bool => $item->account !== null)
            ->groupBy(fn (SharedTransactionNotificationItem $item): int => (int) $item->account_id)
            ->map(fn (Collection $accountItems): array => $this->accountGroup($user, $accountItems))
            ->values();

        return [
            'globalSummary' => $this->globalSummary($itemsByAccount),
            'accountsSummary' => $itemsByAccount->map(fn (array $group): array => $group['summary'])->all(),
            'itemsByAccount' => $itemsByAccount->all(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $itemsByAccount
     * @return array<string, float|int>
     */
    private function globalSummary(Collection $itemsByAccount): array
    {
        return [
            'accounts_count' => $itemsByAccount->count(),
            'movements_count' => $itemsByAccount->sum(fn (array $group): int => $group['summary']['movements_count']),
            'income_total' => round((float) $itemsByAccount->sum(fn (array $group): float => $group['summary']['income_total']), 2),
            'outcome_total' => round((float) $itemsByAccount->sum(fn (array $group): float => $group['summary']['outcome_total']), 2),
            'net_total' => round((float) $itemsByAccount->sum(fn (array $group): float => $group['summary']['net_total']), 2),
        ];
    }

    /**
     * @param Collection<int, SharedTransactionNotificationItem> $items
     * @return array<string, mixed>
     */
    private function accountGroup(User $user, Collection $items): array
    {
        /** @var Account $account */
        $account = $items->first()->account;
        $incomeTotal = $this->movementTotal($items, TransactionType::Income);
        $outcomeTotal = $this->movementTotal($items, TransactionType::Outcome);
        $memberSummary = $this->buildAccountMemberSummary->execute($account);

        return [
            'account' => $account,
            'summary' => [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'balance' => $account->updateBalance(),
                'por_pagar' => $this->reimbursementTotal($memberSummary, $user, 'from_user_id'),
                'por_recibir' => $this->reimbursementTotal($memberSummary, $user, 'to_user_id'),
                'movements_count' => $items->count(),
                'income_total' => $incomeTotal,
                'outcome_total' => $outcomeTotal,
                'net_total' => round($incomeTotal - $outcomeTotal, 2),
            ],
            'items' => $items->values(),
        ];
    }

    /**
     * @param Collection<int, SharedTransactionNotificationItem> $items
     */
    private function movementTotal(Collection $items, TransactionType $type): float
    {
        return round((float) $items
            ->filter(fn (SharedTransactionNotificationItem $item): bool => $item->action !== SharedTransactionNotificationAction::Settled)
            ->filter(fn (SharedTransactionNotificationItem $item): bool => $item->type === $type)
            ->sum('amount'), 2);
    }

    /**
     * @param array<string, mixed> $memberSummary
     */
    private function reimbursementTotal(array $memberSummary, User $user, string $userKey): float
    {
        return round((float) collect($memberSummary['pending_reimbursements'])
            ->filter(fn (array $item): bool => (int) $item[$userKey] === $user->id)
            ->sum('amount'), 2);
    }
}
