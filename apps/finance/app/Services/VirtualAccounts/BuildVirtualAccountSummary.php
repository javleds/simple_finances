<?php

declare(strict_types=1);

namespace App\Services\VirtualAccounts;

use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Queries\VisibleAccountsForUser;
use Illuminate\Support\Collection;

class BuildVirtualAccountSummary
{
    public function __construct(
        private readonly VisibleAccountsForUser $visibleAccountsForUser,
    ) {
    }

    public function execute(int $userId): array
    {
        $accounts = $this->visibleAccountsForUser
            ->query($userId)
            ->where('virtual', true)
            ->where('credit_card', false)
            ->with(['balanceSnapshots' => fn ($query) => $query->orderByDesc('observed_at')->orderByDesc('id')])
            ->orderBy('name')
            ->get();

        $items = $accounts->map(fn (Account $account): array => $this->accountSummary($account));

        return [
            'summary'  => $this->globalSummary($items),
            'accounts' => $items->values(),
        ];
    }

    private function accountSummary(Account $account): array
    {
        $manualIncome = $this->manualTransactionTotal($account, TransactionType::Income);
        $manualOutcome = $this->manualTransactionTotal($account, TransactionType::Outcome);
        $observedYield = Decimal::normalize($account->balanceSnapshots()->sum('delta'));
        $firstSnapshot = $account->balanceSnapshots()->orderBy('observed_at')->orderBy('id')->first();
        $latestSnapshot = $account->balanceSnapshots()->orderByDesc('observed_at')->orderByDesc('id')->first();

        return [
            'account_id'           => $account->id,
            'account_name'         => $account->name,
            'color'                => $account->color,
            'current_balance'      => Decimal::normalize($account->balance),
            'initial_balance'      => $firstSnapshot?->previous_balance ?? '0.00',
            'manual_contributions' => $manualIncome,
            'manual_withdrawals'   => $manualOutcome,
            'net_capital'          => Decimal::subtract($manualIncome, $manualOutcome),
            'observed_yield'       => $observedYield,
            'latest_snapshot'      => $latestSnapshot,
        ];
    }

    private function manualTransactionTotal(Account $account, TransactionType $type): string
    {
        return Decimal::normalize(Transaction::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('type', $type)
            ->whereNull('legacy_migrated_at')
            ->whereNull('account_balance_snapshot_id')
            ->sum('amount'));
    }

    private function globalSummary(Collection $items): array
    {
        return [
            'current_balance'      => Decimal::sum($items, 'current_balance'),
            'initial_balance'      => Decimal::sum($items, 'initial_balance'),
            'manual_contributions' => Decimal::sum($items, 'manual_contributions'),
            'manual_withdrawals'   => Decimal::sum($items, 'manual_withdrawals'),
            'net_capital'          => Decimal::sum($items, 'net_capital'),
            'observed_yield'       => Decimal::sum($items, 'observed_yield'),
            'accounts_count'       => $items->count(),
        ];
    }
}
