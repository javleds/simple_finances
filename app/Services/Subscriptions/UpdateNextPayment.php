<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

class UpdateNextPayment
{
    public function handle(Subscription $subscription, ?CarbonImmutable $referenceDate = null): Subscription
    {
        if ($subscription->isFinished()) {
            return $subscription;
        }

        if ($referenceDate === null) {
            $referenceDate = CarbonImmutable::now()->startOfDay();
        }

        if ($subscription->next_payment_date !== null) {
            $nextPaymentDate = CarbonImmutable::instance($subscription->next_payment_date)->startOfDay();

            if ($nextPaymentDate->isAfter($referenceDate)) {
                return $subscription;
            }
        }

        $startedAt = CarbonImmutable::instance($subscription->started_at)->startOfDay();

        if ($subscription->isYearly()) {
            $nextCandidateDate = $startedAt->setYear($referenceDate->year);

            if ($nextCandidateDate->isBefore($referenceDate)) {
                $nextCandidateDate = $nextCandidateDate->addYear();
            }

            $subscription->next_payment_date = $nextCandidateDate;
            $subscription->saveQuietly();

            return $subscription;
        }

        if ($subscription->isMonthly()) {
            $nextCandidateDate = $startedAt
                ->setYear($referenceDate->year)
                ->setMonth($referenceDate->month);

            if ($nextCandidateDate->isBefore($referenceDate)) {
                $nextCandidateDate = $nextCandidateDate->addMonth();
            }

            $subscription->next_payment_date = $nextCandidateDate;
            $subscription->saveQuietly();

            return $subscription;
        }

        if ($subscription->isDaily()) {
            // Unsupported for now.
        }

        return $subscription;
    }
}
