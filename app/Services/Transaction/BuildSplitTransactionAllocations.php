<?php

namespace App\Services\Transaction;

use App\Dto\SplitTransactionAllocationDto;
use App\Dto\UserPaymentDto;

class BuildSplitTransactionAllocations
{
    public function execute(float $amount, array $userPayments): array
    {
        $positivePayments = array_values(array_filter(
            $userPayments,
            fn (UserPaymentDto $payment): bool => $payment->percentage > 0
        ));

        if ($positivePayments === []) {
            return [];
        }

        $lastIndex = count($positivePayments) - 1;
        $allocatedAmount = 0.0;
        $allocations = [];

        foreach ($positivePayments as $index => $payment) {
            $paymentAmount = $index === $lastIndex
                ? round($amount - $allocatedAmount, 2)
                : round($amount * ($payment->percentage / 100), 2);

            $allocatedAmount += $paymentAmount;
            $allocations[] = new SplitTransactionAllocationDto(
                userId: $payment->userId,
                percentage: $payment->percentage,
                amount: $paymentAmount,
            );
        }

        return $allocations;
    }
}
