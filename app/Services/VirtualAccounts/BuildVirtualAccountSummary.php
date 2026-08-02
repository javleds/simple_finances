<?php

namespace App\Services\VirtualAccounts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Accounts\VisibleAccountsForUser;
use Illuminate\Support\Collection;

class BuildVirtualAccountSummary
{
    public function __construct(
        private readonly VisibleAccountsForUser $visibleAccountsForUser,
    ) {}

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
            'summary' => $this->globalSummary($items),
            'accounts' => $items->values(),
        ];
    }

    private function accountSummary(Account $account): array
    {
        $manualIncome = $this->manualTransactionTotal($account, TransactionType::Income);
        $manualOutcome = $this->manualTransactionTotal($account, TransactionType::Outcome);
        $observedYield = (float) $account->balanceSnapshots()->sum('delta');
        $firstSnapshot = $account->balanceSnapshots()->orderBy('observed_at')->orderBy('id')->first();
        $latestSnapshot = $account->balanceSnapshots()->orderByDesc('observed_at')->orderByDesc('id')->first();

        return [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'color' => $account->color,
            'current_balance' => (float) $account->balance,
            'initial_balance' => $firstSnapshot?->previous_balance ?? 0.0,
            'manual_contributions' => $manualIncome,
            'manual_withdrawals' => $manualOutcome,
            'net_capital' => $manualIncome - $manualOutcome,
            'observed_yield' => $observedYield,
            'latest_snapshot' => $latestSnapshot,
        ];
    }

    private function manualTransactionTotal(Account $account, TransactionType $type): float
    {
        return (float) Transaction::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('type', $type)
            ->whereNull('legacy_migrated_at')
            ->whereNull('account_balance_snapshot_id')
            ->sum('amount');
    }

    private function globalSummary(Collection $items): array
    {
        return [
            'current_balance' => round((float) $items->sum('current_balance'), 2),
            'initial_balance' => round((float) $items->sum('initial_balance'), 2),
            'manual_contributions' => round((float) $items->sum('manual_contributions'), 2),
            'manual_withdrawals' => round((float) $items->sum('manual_withdrawals'), 2),
            'net_capital' => round((float) $items->sum('net_capital'), 2),
            'observed_yield' => round((float) $items->sum('observed_yield'), 2),
            'accounts_count' => $items->count(),
        ];
    }
}
