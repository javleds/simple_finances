<?php

namespace App\Listeners;

use App\Models\NotificationType;
use App\Models\User;
use Filament\Events\Auth\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AfterUserCreated
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $user = User::find($event->getUser()->id);
        $notificationTypes = NotificationType::whereIn('name',NotificationType::DEFAULT_NOTIFICATIONS)
            ->get()
            ->pluck('id')
            ->toArray();

        $user->notificationTypes()->sync($notificationTypes);
    }
}
