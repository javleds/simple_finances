<?php

namespace App\Services\Dashboard;

use App\Services\Accounts\VisibleAccountsForUser;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Builder;

class BuildDashboardAccounts
{
    public function __construct(
        private readonly Guard $auth,
        private readonly VisibleAccountsForUser $visibleAccountsForUser,
    ) {}

    public function execute(): array
    {
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
                'pending_total' => 0.0,
            ],
            'pending_actions' => [],
        ];
    }

    private function visibleAccounts(): Builder
    {
        return $this->visibleAccountsForUser
            ->query($this->auth->id());
    }
}
