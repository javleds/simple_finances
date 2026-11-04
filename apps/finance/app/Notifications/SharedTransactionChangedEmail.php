<?php

declare(strict_types=1);

namespace App\Notifications;

use function app;

use App\Services\Accounts\RecalculateAccountBalance;
use App\Support\SpaUrl;

use function auth;
use function config;

use FinSi\FinanceDomain\Enums\Action;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use function sprintf;

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
    ) {
    }

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
        return (new MailMessage())->markdown('mail.shared.transactions.changed', [
            'modifier'    => auth()->user(),
            'user'        => $this->user,
            'transaction' => $this->transaction,
            'balance'     => app(RecalculateAccountBalance::class)->execute($this->transaction->account)->balance,
            'action'      => $this->action,
            'link'        => $this->link(),
        ])->subject(sprintf('%s - Movimiento en cuenta compartida', config('app.name')));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
        ];
    }

    private function link(): string
    {
        return app(SpaUrl::class)->to('accounts/' . $this->transaction->account_id);
    }
}
