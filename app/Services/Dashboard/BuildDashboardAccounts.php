<?php

namespace App\Services\Dashboard;

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Contracts\Auth\Guard;

class BuildDashboardAccounts
{
    public function __construct(private Guard $auth) {}

    public function execute(): array
    {
        $pendingActions = $this->pendingActions();

        return [
            'summary' => [
                'active_accounts' => Account::query()->count(),
                'shared_accounts' => Account::query()
                    ->has('users', '>', 1)
                    ->count(),
                'pending_total' => $pendingActions->sum('amount'),
            ],
            'pending_actions' => $pendingActions->values()->all(),
        ];
    }

    private function pendingActions(): \Illuminate\Support\Collection
    {
        return Transaction::query()
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
}
