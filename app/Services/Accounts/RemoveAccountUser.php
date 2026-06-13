<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveAccountUser
{
    public function execute(Account $account, User $user): void
    {
        DB::transaction(function () use ($account, $user): void {
            $deletedPercentage = $this->accountUserPercentage($account, $user);

            $account->users()->detach($user->id);

            if ($deletedPercentage <= 0.0) {
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
                    'percentage' => round((float) $oldestRemainingUser->percentage + $deletedPercentage, 2),
                    'updated_at' => now(),
                ]);
        });
    }

    private function accountUserPercentage(Account $account, User $user): float
    {
        $accountUser = DB::table('account_user')
            ->where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first(['percentage']);

        if (! $accountUser) {
            return 0.0;
        }

        return round((float) $accountUser->percentage, 2);
    }
}
