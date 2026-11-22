<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Dto\SplitTransactionAllocationDto;
use App\Dto\UserPaymentDto;

use function array_filter;
use function array_values;
use function count;

use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Validation\ValidationException;

class BuildSplitTransactionAllocations
{
    public function execute(string $amount, array $userPayments): array
    {
        $positivePayments = array_values(array_filter(
            $userPayments,
            fn (UserPaymentDto $payment): bool => Decimal::compare($payment->percentage, 0) > 0
        ));

        if ([] === $positivePayments) {
            return [];
        }

        $lastIndex = count($positivePayments) - 1;
        $allocatedAmount = '0.00';
        $allocations = [];

        foreach ($positivePayments as $index => $payment) {
            $paymentAmount = $index === $lastIndex
                ? Decimal::subtract($amount, $allocatedAmount)
                : Decimal::ratio($amount, $payment->percentage, 100);

            if (Decimal::compare($paymentAmount, 0) < 0) {
                throw ValidationException::withMessages(['user_payments' => 'The selected percentages cannot be allocated without a negative remainder.']);
            }

            $allocatedAmount = Decimal::add($allocatedAmount, $paymentAmount);
            $allocations[] = new SplitTransactionAllocationDto(
                userId: $payment->userId,
                percentage: $payment->percentage,
                amount: $paymentAmount,
            );
        }

        return $allocations;
    }
}
