<?php

declare(strict_types=1);

namespace App\Services\SharedTransactions;

use function app;

use App\Services\Accounts\BuildAccountMemberSummary;

use function collect;

use FinSi\FinanceDomain\Enums\SharedTransactionNotificationAction;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\SharedTransactionNotificationItem;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Collection;

class BuildSharedTransactionNotificationMailData
{
    public function __construct(private readonly BuildAccountMemberSummary $buildAccountMemberSummary)
    {
    }

    /**
     * @param Collection<int, SharedTransactionNotificationItem> $items
     *
     * @return array<string, mixed>
     */
    public function execute(User $user, Collection $items): array
    {
        $itemsByAccount = $items
            ->filter(fn (SharedTransactionNotificationItem $item): bool => null !== $item->account)
            ->groupBy(fn (SharedTransactionNotificationItem $item): int => (int) $item->account_id)
            ->map(fn (Collection $accountItems): array => $this->accountGroup($user, $accountItems))
            ->values();

        return [
            'globalSummary'   => $this->globalSummary($itemsByAccount),
            'accountsSummary' => $itemsByAccount->map(fn (array $group): array => $group['summary'])->all(),
            'itemsByAccount'  => $itemsByAccount->all(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $itemsByAccount
     *
     * @return array<string, string|int>
     */
    private function globalSummary(Collection $itemsByAccount): array
    {
        return [
            'accounts_count'  => $itemsByAccount->count(),
            'movements_count' => $itemsByAccount->sum(fn (array $group): int => $group['summary']['movements_count']),
            'income_total'    => Decimal::sum($itemsByAccount->pluck('summary.income_total')),
            'outcome_total'   => Decimal::sum($itemsByAccount->pluck('summary.outcome_total')),
            'net_total'       => Decimal::sum($itemsByAccount->pluck('summary.net_total')),
        ];
    }

    /**
     * @param Collection<int, SharedTransactionNotificationItem> $items
     *
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
                'account_id'      => $account->id,
                'account_name'    => $account->name,
                'balance'         => app(\App\Services\Accounts\RecalculateAccountBalance::class)->execute($account)->balance,
                'por_pagar'       => $this->reimbursementTotal($memberSummary, $user, 'from_user_id'),
                'por_recibir'     => $this->reimbursementTotal($memberSummary, $user, 'to_user_id'),
                'movements_count' => $items->count(),
                'income_total'    => $incomeTotal,
                'outcome_total'   => $outcomeTotal,
                'net_total'       => Decimal::subtract($incomeTotal, $outcomeTotal),
            ],
            'items'   => $items->values(),
        ];
    }

    /**
     * @param Collection<int, SharedTransactionNotificationItem> $items
     */
    private function movementTotal(Collection $items, TransactionType $type): string
    {
        return Decimal::sum($items
            ->filter(fn (SharedTransactionNotificationItem $item): bool => SharedTransactionNotificationAction::Settled !== $item->action)
            ->filter(fn (SharedTransactionNotificationItem $item): bool => $item->type === $type), 'amount');
    }

    /**
     * @param array<string, mixed> $memberSummary
     */
    private function reimbursementTotal(array $memberSummary, User $user, string $userKey): string
    {
        return Decimal::sum(collect($memberSummary['pending_reimbursements'])
            ->filter(fn (array $item): bool => (int) $item[$userKey] === $user->id), 'amount');
    }
}
