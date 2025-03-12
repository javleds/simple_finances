<?php

namespace App\Services;

use App\Models\Account;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\WeeklySummaryNotification;

readonly class WeeklySummaryProcessor
{
    public function __construct(private AccountSummaryCreator $accountSummaryCreator)
    {
    }

    public function handle(): void
    {
        $users = User::withoutGlobalScopes()
            ->with('accounts')
            ->whereHas('notificationTypes', function ($query) {
                $query->where('name', NotificationType::WEEKLY_SUMMARY);
            })
            ->get();

        $users->each(function (User $user) {
            $attachments = [];
            foreach ($user->accounts as $account) {
                $path = $this->accountSummaryCreator->handle($user, $account);
                $attachments[$path] = [
                    'as' => sprintf('%s_%s.csv', str($account->name)->slug('_'), date('Y_m')),
                    'mime' => 'text/csv'
                ];
            }

            $user->notify(new WeeklySummaryNotification($user, $attachments));

            foreach ($attachments as $path => $attachment) {
                unlink($path);
            }
        });
    }
}
