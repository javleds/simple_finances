<?php

namespace App\Dto;

use App\Models\Subscription;

class SubscriptionProjection
{
    public string $name;
    public string $frequency;
    public float $amount;
    public float $projectionAmount;

    public function __construct(string $name, string $frequency, float $amount, float $projectionAmount)
    {
        $this->name = $name;
        $this->frequency = $frequency;
        $this->amount = $amount;
        $this->projectionAmount = $projectionAmount;
    }

    public static function fromSubscriptionProjection(Subscription $subscription, float $computedAmount): self
    {
        return new static(
            $subscription->name,
            sprintf('%s %s', $subscription->frequency_unit, $subscription->frequency_type->getLabel()),
            $subscription->amount,
            $computedAmount
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'frequency' => $this->frequency,
            'amount' => $this->amount,
            'projectionAmount' => $this->projectionAmount,
        ];
    }
}
