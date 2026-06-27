<?php

namespace App\Services\Dashboard;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Accounts\VisibleAccountsForUser;
use App\Services\Transaction\VisibleTransactionsForUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Auth\Guard;

class BuildDashboardAccounts
{
    public function __construct(
        private readonly Guard $auth,
        private readonly VisibleAccountsForUser $visibleAccountsForUser,
        private readonly VisibleTransactionsForUser $visibleTransactionsForUser,
    ) {}

    public function execute(): array
    {
        $pendingActions = $this->pendingActions();
        $visibleAccounts = $this->visibleAccounts();

        return [
            'summary' => [
                'active_accounts' => (clone $visibleAccounts)
                    ->where('virtual', false)
                    ->count(),
                'virtual_accounts' => (clone $visibleAccounts)
                    ->where('virtual', true)
                    ->count(),
                'shared_accounts' => (clone $visibleAccounts)
                    ->has('users', '>', 1)
                    ->count(),
                'pending_total' => $pendingActions->sum('amount'),
            ],
            'pending_actions' => $pendingActions->values()->all(),
        ];
    }

    private function pendingActions(): \Illuminate\Support\Collection
    {
        return $this->visibleTransactionsForUser
            ->query($this->auth->id())
            ->with('account')
            ->where('status', TransactionStatus::Pending)
            ->where('user_id', $this->auth->id())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'id' => 'tx-'.$transaction->id,
                'account_id' => $transaction->account_id,
                'account_name' => $transaction->account->name,
                'account_color' => $transaction->account->color,
                'concept' => $transaction->concept,
                'amount' => $transaction->amount,
                'date' => $transaction->scheduled_at->toDateString(),
            ]);
    }

    private function visibleAccounts(): Builder
    {
        return $this->visibleAccountsForUser
            ->query($this->auth->id());
    }
}
