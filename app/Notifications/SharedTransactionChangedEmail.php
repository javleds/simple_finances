<?php

namespace App\Notifications;

use App\Enums\Action;
use App\Filament\Resources\AccountResource\Pages\ViewAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Support\SpaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SharedTransactionChangedEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly User $user,
        public readonly Transaction $transaction,
        public readonly Action $action,
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
        return (new MailMessage)->markdown('mail.shared.transactions.changed', [
            'modifier' => auth()->user(),
            'user' => $this->user,
            'transaction' => $this->transaction,
            'action' => $this->action,
            'link' => $this->link(),
        ])->subject(sprintf('%s - Movimiento en cuenta compartida', config('app.name')));
    }

    private function link(): string
    {
        if ($this->useSpaUrl) {
            return app(SpaUrl::class)->to('accounts/'.$this->transaction->account_id);
        }

        return ViewAccount::getUrl([$this->transaction->account_id]);
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
