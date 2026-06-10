<?php

namespace App\Notifications;

use App\Models\AccountInvite;
use App\Services\Auth\ResolveInvitePostAuthRedirect;
use App\Support\SpaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteAccountApiEmail extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AccountInvite $invite,
        private readonly string $authPath = 'register',
    )
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
            'link' => app(SpaUrl::class)->to($this->authPath, [
                'email' => $this->invite->email,
                'post_auth_action' => ResolveInvitePostAuthRedirect::ACCOUNT_INVITES_ACTION,
            ]),
        ])->subject(sprintf('%s - Invitación a cuenta compartida', config('app.name')));
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
