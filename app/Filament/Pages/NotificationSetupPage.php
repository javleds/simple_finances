<?php

namespace App\Filament\Pages;

use App\Services\NotificableAccountSetupBuilder;
use App\Services\NotificationSetupBuilder;
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

    #[Locked]
    public array $notificableAccounts;

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

            Notification::make('notification_setup_saved_off'.$notificationId)->title('Preferencia guardada.')->success()->send();

            return;
        }

        $user->notificationTypes()->attach($notificationId);
        $this->refetch();

        Notification::make('notification_setup_saved_on'.$notificationId)->title('Preferencia guardada.')->success()->send();
    }

    public function handleAccountNotification(int $accountId, int $enabled): void
    {
        $user = auth()->user();

        if ($enabled === 1) {
            $user->notificableAccounts()->detach($accountId);
            $this->refetch();

            Notification::make('account_notification_saved_off'.$accountId)->title('Preferencia guardada.')->success()->send();

            return;
        }

        $user->notificableAccounts()->attach($accountId);
        $this->refetch();

        Notification::make('account_notification_saved_on'.$accountId)->title('Preferencia guardada.')->success()->send();
    }

    public function refetch(): void
    {
        $user = auth()->user();
        $this->notificationTypes = app(NotificationSetupBuilder::class)->handle($user);
        $this->notificableAccounts = app(NotificableAccountSetupBuilder::class)->handle($user);
    }
}
