<?php

namespace App\Listeners;

use App\Events\SubscriptionSaving;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class BeforeSubscriptionSaved
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
    public function handle(SubscriptionSaving $event): void
    {
        $event->subscription->next_payment_date = $event->subscription->getNextPaymentDate();
        $event->subscription->previous_payment_date = $event->subscription->getPreviousPaymentDate();
    }
}
