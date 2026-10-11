<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Models\PersonalMoneySetting;

use function ceil;

use Illuminate\Support\Collection;

use function max;
use function min;
use function round;

class BuildPersonalMoneySummary
{
    public function __construct(
        private readonly EligiblePersonalMoneyAccounts $eligibleAccounts,
        private readonly BuildPersonalMoneySources $buildSources,
    ) {
    }

    public function execute(int $userId, int $page = 1, int $perPage = 20, string $resolution = 'all'): array
    {
        $accounts = $this->eligibleAccounts->execute($userId)->orderBy('name')->get(['id', 'name']);
        $setting = PersonalMoneySetting::query()->where('user_id', $userId)->first();
        $accountAvailable = null !== $setting && $accounts->contains('id', $setting->account_id);
        $sources = $setting?->enabled && $accountAvailable ? $this->buildSources->execute($userId, $setting) : new Collection();
        $pendingCount = $sources->where('resolution', 'pending')->count();
        $available = $setting?->enabled && $accountAvailable ? round($setting->opening_balance + $sources->sum('effect'), 2) : null;
        $history = 'pending' === $resolution ? $sources->where('resolution', 'pending')->values() : $sources;
        $total = $history->count();
        $perPage = min(100, max(1, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        return [
            'settings'          => null === $setting ? null : [
                'account_id'      => $setting->account_id, 'started_at' => $setting->started_at->toDateString(),
                'opening_balance' => $setting->opening_balance, 'enabled' => $setting->enabled,
            ],
            'accounts'          => $accounts->toArray(),
            'account_available' => $accountAvailable,
            'available_balance' => $available,
            'pending_count'     => $pendingCount,
            'history'           => $history->slice(($page - 1) * $perPage, $perPage)->map(static function (array $source): array {
                unset($source['source_signature'], $source['source_account_id']);

                return $source;
            })->values()->all(),
            'meta'              => ['current_page' => $page, 'last_page' => $lastPage, 'total' => $total],
        ];
    }
}
