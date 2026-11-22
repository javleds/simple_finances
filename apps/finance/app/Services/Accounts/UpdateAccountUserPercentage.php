<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAccountUserPercentage
{
    public function execute(Account $account, int $userId, string $percentage): Collection
    {
        return DB::transaction(function () use ($account, $userId, $percentage): Collection {
            $users = $account->users()
                ->withPivot('percentage')
                ->orderBy('users.id')
                ->get();

            if (1 === $users->count()) {
                $account->users()->updateExistingPivot($userId, ['percentage' => '100.00']);

                return $this->accountUsers($account);
            }

            $fixedPercentage = Decimal::round($percentage, 2);
            $remainingPercentage = Decimal::subtract('100.00', $fixedPercentage);
            $otherUsers = $users->where('id', '!=', $userId)->values();
            $otherPercentages = $this->distributeRemainingPercentage($otherUsers, $remainingPercentage);

            $account->users()->updateExistingPivot($userId, ['percentage' => $fixedPercentage]);

            foreach ($otherPercentages as $otherUserId => $otherPercentage) {
                $account->users()->updateExistingPivot($otherUserId, ['percentage' => $otherPercentage]);
            }

            return $this->accountUsers($account);
        });
    }

    private function distributeRemainingPercentage(Collection $users, string $remainingPercentage): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $currentTotal = Decimal::sum($users->map(fn (User $user): string => Decimal::normalize($user->pivot->percentage)));

        if (Decimal::compare($currentTotal, '0.00') <= 0) {
            return $this->distributeEvenly($users, $remainingPercentage);
        }

        return $this->distributeProportionally($users, $remainingPercentage, $currentTotal);
    }

    private function distributeEvenly(Collection $users, string $remainingPercentage): array
    {
        $lastIndex = $users->count() - 1;
        $allocatedPercentage = '0.00';
        $percentages = [];

        foreach ($users as $index => $user) {
            $percentage = $index === $lastIndex
                ? Decimal::subtract($remainingPercentage, $allocatedPercentage)
                : Decimal::divide($remainingPercentage, $users->count());

            if (Decimal::compare($percentage, 0) < 0) {
                throw ValidationException::withMessages(['percentage' => 'The percentages cannot be allocated without a negative remainder.']);
            }
            $allocatedPercentage = Decimal::add($allocatedPercentage, $percentage);
            $percentages[$user->id] = $percentage;
        }

        return $percentages;
    }

    private function distributeProportionally(Collection $users, string $remainingPercentage, string $currentTotal): array
    {
        $lastIndex = $users->count() - 1;
        $allocatedPercentage = '0.00';
        $percentages = [];

        foreach ($users as $index => $user) {
            $percentage = $index === $lastIndex
                ? Decimal::subtract($remainingPercentage, $allocatedPercentage)
                : Decimal::ratio($remainingPercentage, Decimal::normalize($user->pivot->percentage), $currentTotal);

            if (Decimal::compare($percentage, 0) < 0) {
                throw ValidationException::withMessages(['percentage' => 'The percentages cannot be allocated without a negative remainder.']);
            }
            $allocatedPercentage = Decimal::add($allocatedPercentage, $percentage);
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
