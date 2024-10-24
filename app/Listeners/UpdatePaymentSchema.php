<?php

namespace App\Listeners;

use App\Enums\PaymentStatus;
use App\Events\SubscriptionSaved;
use App\Models\SubscriptionPayment;
use App\Services\GenerateSubscriptionPaymentSchema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
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
