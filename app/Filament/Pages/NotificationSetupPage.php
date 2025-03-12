<?php

namespace App\Filament\Pages;

use App\Models\NotificationType;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class NotificationSetupPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.notification-setup-page';

    protected static ?string $title = 'Configuración de notificaciones';

    #[Locked]
    public array $notificationTypes;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->refetch();
    }

    public function handleNotification(int $notificationId, int $enabled): void
    {
        $user = auth()->user();

        if ($enabled === 1) {
            $user->notificationTypes()->detach($notificationId);
            $this->refetch();

            Notification::make('notification_setup_saved_off' . $notificationId)->title('Preferencia guardada.')->success()->send();

            return;
        }

        $user->notificationTypes()->attach($notificationId);
        $this->refetch();

        Notification::make('notification_setup_saved_on' . $notificationId)->title('Preferencia guardada.')->success()->send();
    }

    public function refetch(): void
    {
        $userNotifications = auth()->user()->notificationTypes()->get()->toArray();
        $notificationTypes = NotificationType::all()->map(fn (NotificationType $n) => array_merge($n->toArray(), ['checked' => 0]))->toArray();

        foreach ($userNotifications as $userNotification) {
            foreach ($notificationTypes as $key => $notificationType) {
                if ($userNotification['id'] === $notificationType['id']) {
                    $notificationTypes[$key]['checked'] = 1;
                }
            }
        }

        $this->notificationTypes = $notificationTypes;
    }
}
