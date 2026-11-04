<?php

namespace App\Listeners;

use App\Events\SubscriptionSaved;
use App\Services\GenerateSubscriptionPaymentSchema;
use Illuminate\Support\Carbon;

class UpdatePaymentSchema
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly GenerateSubscriptionPaymentSchema $paymentSchema)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SubscriptionSaved $event): void
    {
        $startDate = $event->subscription->started_at;
        $endDate = Carbon::now()->endOfYear();

        if ($event->subscription->payments()->count() !== 0) {
            $startDate = Carbon::now();
        }

        $this->paymentSchema->handle($event->subscription, $startDate, $endDate);
    }
}
