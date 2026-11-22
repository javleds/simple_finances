<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Facades\DB;

use function now;

class RemoveAccountUser
{
    public function execute(Account $account, User $user): void
    {
        DB::transaction(function () use ($account, $user): void {
            $deletedPercentage = $this->accountUserPercentage($account, $user);

            $account->users()->detach($user->id);

            if (Decimal::compare($deletedPercentage, '0.00') <= 0) {
                return;
            }

            $oldestRemainingUser = DB::table('account_user')
                ->where('account_id', $account->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->first(['id', 'percentage']);

            if (! $oldestRemainingUser) {
                return;
            }

            DB::table('account_user')
                ->where('id', $oldestRemainingUser->id)
                ->update([
                    'percentage' => Decimal::add(Decimal::normalize($oldestRemainingUser->percentage), $deletedPercentage),
                    'updated_at' => now(),
                ]);
        });
    }

    private function accountUserPercentage(Account $account, User $user): string
    {
        $accountUser = DB::table('account_user')
            ->where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first(['percentage']);

        if (! $accountUser) {
            return '0.00';
        }

        return Decimal::normalize($accountUser->percentage);
    }
}
