<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpdateAccountUserPercentage
{
    public function execute(Account $account, int $userId, float $percentage): Collection
    {
        return DB::transaction(function () use ($account, $userId, $percentage): Collection {
            $users = $account->users()
                ->withPivot('percentage')
                ->orderBy('users.id')
                ->get();

            if ($users->count() === 1) {
                $account->users()->updateExistingPivot($userId, ['percentage' => 100.0]);

                return $this->accountUsers($account);
            }

            $fixedPercentage = round($percentage, 2);
            $remainingPercentage = round(100.0 - $fixedPercentage, 2);
            $otherUsers = $users->where('id', '!=', $userId)->values();
            $otherPercentages = $this->distributeRemainingPercentage($otherUsers, $remainingPercentage);

            $account->users()->updateExistingPivot($userId, ['percentage' => $fixedPercentage]);

            foreach ($otherPercentages as $otherUserId => $otherPercentage) {
                $account->users()->updateExistingPivot($otherUserId, ['percentage' => $otherPercentage]);
            }

            return $this->accountUsers($account);
        });
    }

    private function distributeRemainingPercentage(Collection $users, float $remainingPercentage): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $currentTotal = round(
            $users->sum(fn (User $user): float => (float) $user->pivot->percentage),
            2,
        );

        if ($currentTotal <= 0.0) {
            return $this->distributeEvenly($users, $remainingPercentage);
        }

        return $this->distributeProportionally($users, $remainingPercentage, $currentTotal);
    }

    private function distributeEvenly(Collection $users, float $remainingPercentage): array
    {
        $lastIndex = $users->count() - 1;
        $allocatedPercentage = 0.0;
        $percentages = [];

        foreach ($users as $index => $user) {
            $percentage = $index === $lastIndex
                ? round($remainingPercentage - $allocatedPercentage, 2)
                : round($remainingPercentage / $users->count(), 2);

            $allocatedPercentage += $percentage;
            $percentages[$user->id] = $percentage;
        }

        return $percentages;
    }

    private function distributeProportionally(Collection $users, float $remainingPercentage, float $currentTotal): array
    {
        $lastIndex = $users->count() - 1;
        $allocatedPercentage = 0.0;
        $percentages = [];

        foreach ($users as $index => $user) {
            $percentage = $index === $lastIndex
                ? round($remainingPercentage - $allocatedPercentage, 2)
                : round($remainingPercentage * (((float) $user->pivot->percentage) / $currentTotal), 2);

            $allocatedPercentage += $percentage;
            $percentages[$user->id] = $percentage;
        }

        return $percentages;
    }

    private function accountUsers(Account $account): Collection
    {
        return $account->users()
            ->withPivot('percentage')
            ->orderBy('users.name')
            ->get();
    }
}
