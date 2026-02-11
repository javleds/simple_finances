<?php

namespace App\Notifications;

use App\Models\Account;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class SharedTransactionBatchChangedEmail extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly Account $account,
        public readonly Collection $items,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('mail.shared.transactions.batch_changed', [
            'user' => $this->user,
            'account' => $this->account,
            'items' => $this->items,
        ])->subject(sprintf('%s - Movimientos en cuenta compartida', config('app.name')));
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
