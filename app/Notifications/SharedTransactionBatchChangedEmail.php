<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\SharedTransactions\BuildSharedTransactionNotificationMailData;
use App\Support\SpaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class SharedTransactionBatchChangedEmail extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly Collection $items,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mailData = app(BuildSharedTransactionNotificationMailData::class)->execute($this->user, $this->items);

        return (new MailMessage)->markdown('mail.shared.transactions.batch_changed', [
            'user' => $this->user,
            'globalSummary' => $mailData['globalSummary'],
            'accountsSummary' => $mailData['accountsSummary'],
            'itemsByAccount' => $mailData['itemsByAccount'],
            'link' => app(SpaUrl::class)->to('accounts'),
        ])->subject(sprintf('%s - Movimientos en cuentas compartidas', config('app.name')));
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
