<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateSubscriptionPaymentSchema
{
    public function handle(Subscription $subscription, Carbon $startDate, Carbon $endDate): void
    {
        $now = Carbon::now();

        $subscription->payments()
            ->where('scheduled_at', '>', $now)
            ->where('status', PaymentStatus::Pending)
            ->delete();

        if ($subscription->isFinished()) {
            return;
        }

        $userId = auth()->id();

        if ($startDate->day > $subscription->started_at->day) {
            $startDate->add($subscription->getAddFrequency());
        }

        $startDate->setDay($subscription->started_at->day);
        $lastPayment = $subscription->payments()->latest()->first();

        $dates = [];
        while ($startDate->isBefore($endDate)) {
            if ($startDate->isBefore($subscription->started_at)) {
                $startDate->add($subscription->getAddFrequency());

                continue;
            }

            if ($lastPayment !== null && !$startDate->isAfter($lastPayment->scheduled_at)) {
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

//        $payments = DB::select(
//            'SELECT sp.scheduled_at, COUNT(*) total, GROUP_CONCAT(sp.id) ids FROM subscription_payments sp WHERE sp.subscription_id = ? HAVING total > 1',
//            [$subscription->id]
//        );
//
//        $deletableI = [];
//        foreach ($payments as $payment) {
//            $payment['scheduled_at'];
//            $total = $payment['total'];
//            $ids = explode(',', $payment['ids']);
//        }
    }
}
