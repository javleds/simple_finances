<?php

namespace App\Dto;

class SubscriptionSavingsTargetDto
{
    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $name,
        public readonly float $amount,
        public readonly string $nextPaymentDate,
        public readonly string $cycleStartDate,
        public readonly float $targetToday,
    ) {}

    public function toArray(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'name' => $this->name,
            'amount' => $this->amount,
            'next_payment_date' => $this->nextPaymentDate,
            'cycle_start_date' => $this->cycleStartDate,
            'target_today' => $this->targetToday,
        ];
    }
}
