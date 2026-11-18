<?php

declare(strict_types=1);

namespace App\Listeners;

use FinSi\FinanceDomain\Models\NotificationType;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Auth\Events\Registered;

class AfterUserCreated
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $user = User::find($event->user->id);

        if (! $user || $user->isVirtualCustodian()) {
            return;
        }

        $notificationTypes = NotificationType::whereIn('name', NotificationType::DEFAULT_NOTIFICATIONS)
            ->get()
            ->pluck('id')
            ->toArray();

        $user->notificationTypes()->sync($notificationTypes);
    }
}
