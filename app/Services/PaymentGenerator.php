<?php

namespace App\Services;

abstract class PaymentGenerator
{
    protected function adjustLastPayment(array $payments, float $total): array
    {
        $partialSum = collect($payments)->reduce(fn ($accum, $payment) => $accum += $payment['amount'], 0.0);

        $difference = $partialSum - $total;
        $lastAmount = $payments[count($payments) - 1]['amount'];

        $payments[count($payments) - 1]['amount'] = round($lastAmount - $difference, 2);

        return $payments;
    }
}
