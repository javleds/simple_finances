<?php

namespace App\Services\Dashboard;

use App\Models\Account;
use App\Services\Accounts\VisibleAccountsForUser;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Collection;

class BuildDashboardGraph
{
    public function __construct(
        private readonly Guard $auth,
        private readonly VisibleAccountsForUser $visibleAccountsForUser,
    ) {}

    public function execute(): Collection
    {
        return $this->visibleAccountsForUser
            ->query($this->auth->id())
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
