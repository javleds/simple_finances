<?php

namespace App\Notifications;

use App\Models\AccountInvite;
use App\Support\SpaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteAccountApiEmail extends Notification
{
    use Queueable;

    public function __construct(public readonly AccountInvite $invite)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('mail.accounts.invite', [
            'invite' => $this->invite,
            'link' => app(SpaUrl::class)->to('account-invites'),
        ])->subject(sprintf('%s - Invitación a cuenta compartida', config('app.name')));
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
