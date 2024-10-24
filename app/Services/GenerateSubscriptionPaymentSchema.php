<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Carbon;

class GenerateSubscriptionPaymentSchema
{
    public function handle(Subscription $subscription, Carbon $startDate, Carbon $endDate): void
    {
        $now = Carbon::now();

        $subscription->payments()->where('scheduled_at', '>', $now)->delete();

        if ($subscription->isFinished()) {
            return;
        }

        $userId = auth()->id();

        $dates = [];
        while ($startDate->isBefore($endDate)) {
            if ($startDate->isBefore($subscription->started_at)) {
                $startDate->add($subscription->getAddFrequency());

                continue;
            }

            $status = $startDate->isBefore($now)
                ? PaymentStatus::Paid
                : PaymentStatus::Pending;

            $dates[] = [
                'subscription_id' => $subscription->id,
                'amount' => $subscription->amount,
                'status' => $status,
                'scheduled_at' => $startDate->toDateString(),
                'user_id' => $userId,
            ];

            $startDate->add($subscription->getAddFrequency());
        }

        SubscriptionPayment::insert($dates);
    }
}
