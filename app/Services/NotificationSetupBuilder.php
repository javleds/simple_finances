<?php

namespace App\Services;

use App\Models\NotificationType;
use App\Models\User;

class NotificationSetupBuilder
{
    public function handle(User $user): array
    {
        $userNotifications = $user->notificationTypes()->get()->toArray();
        $notificationTypes = NotificationType::all()
            ->map(fn (NotificationType $n) => array_merge($n->toArray(), ['checked' => 0]))->toArray();

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
