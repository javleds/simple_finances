<?php

namespace App\Services;

use App\Enums\Frequency;
use Carbon\Carbon;

class BasedOnNumberOfPaymentsPaymentGenerator extends PaymentGenerator
{
    public function handle(float $total, int $numberOfPayments, int $frequencyUnit, Frequency $frequencyType, string $startDate): array
    {
        $payments = [];
        $partials = round($total / $numberOfPayments, 2);
        $date = Carbon::make($startDate);

        foreach (range(1, $numberOfPayments) as $payment) {
            $payments[] = ['amount' => $partials, 'scheduled_at' => $date];
            $date = $date->clone()->modify(sprintf('+ %s %s', $frequencyUnit, $frequencyType->value));
        }

        return $this->adjustLastPayment($payments, $total);
    }
}
