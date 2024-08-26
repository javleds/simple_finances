<?php

namespace App\Listeners;

use App\Events\TransactionSaved;
use App\Models\User;
use App\Notifications\SharedTransactionChangedEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyOnSharedAccountSaved
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
    public function handle(TransactionSaved $event): void
    {
        /** @var Collection $users */
        $users = $event->transaction->account->users()->get();
        if ($users->count() <= 1) {
            return;
        }

        foreach ($users as $user) {
            if ($user->id === auth()->id()) {
                continue;
            }

            Notification::send($user, new SharedTransactionChangedEmail($user, $event->transaction));
        }
    }
}