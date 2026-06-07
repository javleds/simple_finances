<?php

namespace App\Services\Accounts;

use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpdateAccountUsersPercentages
{
    public function execute(Account $account, array $users): Collection
    {
        return DB::transaction(function () use ($account, $users): Collection {
            foreach ($users as $user) {
                $account->users()->updateExistingPivot($user['user_id'], [
                    'percentage' => $user['percentage'],
                ]);
            }

            $usersById = collect($users)
                ->keyBy('user_id');

            return $account->users()
                ->whereIn('users.id', $usersById->keys())
                ->get()
                ->sortBy(fn ($user): int => array_search($user->id, $usersById->keys()->all(), true))
                ->values()
                ->map(function ($user) use ($usersById): array {
                    $percentage = (float) $usersById->get($user->id)['percentage'];

                    return [
                        'id' => $user->id,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'percentage' => number_format($percentage, 2, '.', ''),
                    ];
                });
        });
    }
}
