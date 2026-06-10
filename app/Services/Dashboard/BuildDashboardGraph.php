<?php

namespace App\Services\Dashboard;

use App\Models\Account;
use Illuminate\Support\Collection;

class BuildDashboardGraph
{
    public function execute(): Collection
    {
        return Account::query()
            ->orderBy('name')
            ->get(['id', 'name', 'balance', 'color', 'virtual'])
            ->map(fn (Account $account): array => [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'balance' => $account->balance,
                'color' => $account->color,
                'is_virtual' => $account->virtual,
            ]);
    }
}
