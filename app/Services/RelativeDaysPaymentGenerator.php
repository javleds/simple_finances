<?php

namespace App\Services;

use App\Enums\Frequency;
use Carbon\Carbon;

class RelativeDaysPaymentGenerator extends PaymentGenerator
{
    public function handle(float $total, string $startDate, array $paymentsByDay): array
    {
        if ($paymentsByDay === []) {
            return [];
        }

        $paymentsByDay = collect($paymentsByDay)->sortBy('day');

        $payments = [];
        $date = Carbon::make($startDate);

        $partialSum = 0.0;
        $dayIndex = 0;

        while (true) {
            $index = $dayIndex % count($paymentsByDay);

            $day = intval($paymentsByDay[$dayIndex % count($paymentsByDay)]['day']);
            $amount = floatval($paymentsByDay[$dayIndex % count($paymentsByDay)]['amount']);

            $date->setDay($day);
            $payments[] = ['amount' => $amount, 'scheduled_at' => $date];

            if ($index === count($payments) - 1) {
                $date = $date->clone()->firstOfMonth()->addMonth();
            }

            $partialSum += $amount;

            if ($partialSum >= $total) {
                break;
            }

            $dayIndex++;
        }


        return $this->adjustLastPayment($payments, $total, $partialSum);
    }
}
