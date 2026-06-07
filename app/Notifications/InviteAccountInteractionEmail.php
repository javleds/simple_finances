<?php

namespace App\Notifications;

use App\Models\Account;
use App\Models\AccountInvite;
use App\Support\SpaUrl;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteAccountInteractionEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly AccountInvite $invite,
        private readonly bool $useSpaUrl = false,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('mail.accounts.invite.interact', [
            'invite' => $this->invite,
            'account' => Account::withoutGlobalScopes()->find($this->invite->account_id),
            'link' => $this->link(),
        ])->subject(sprintf('%s - Respuesta a invitación', config('app.name')));
    }

    private function link(): string
    {
        if ($this->useSpaUrl) {
            return app(SpaUrl::class)->to('accounts/'.$this->invite->account_id);
        }

        return Filament::getUrl();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
