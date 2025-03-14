<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;

class NotificableAccountSetupBuilder
{
    public function handle(User $user): array
    {
        $userNotifications = $user->notificableAccounts()->get()->toArray();
        $notificationTypes = Account::all()
            ->map(fn (Account $n) => array_merge($n->toArray(), ['checked' => 0]))->toArray();

        foreach ($userNotifications as $userNotification) {
            foreach ($notificationTypes as $key => $notificationType) {
                if ($userNotification['id'] === $notificationType['id']) {
                    $notificationTypes[$key]['checked'] = 1;
                }
            }
        }

        return $notificationTypes;
    }
}
