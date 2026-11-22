<?php

declare(strict_types=1);

namespace App\Dto;

use FinSi\FinanceDomain\Models\Subscription;

use function sprintf;

class SubscriptionProjection
{
    public string $name;

    public string $frequency;

    public string $amount;

    public string $projectionAmount;

    public function __construct(string $name, string $frequency, string $amount, string $projectionAmount)
    {
        $this->name = $name;
        $this->frequency = $frequency;
        $this->amount = $amount;
        $this->projectionAmount = $projectionAmount;
    }

    public static function fromSubscriptionProjection(Subscription $subscription, string $computedAmount): self
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
            'name'             => $this->name,
            'frequency'        => $this->frequency,
            'amount'           => $this->amount,
            'projectionAmount' => $this->projectionAmount,
        ];
    }
}
