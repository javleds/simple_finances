<?php

declare(strict_types=1);

use FinSi\FinanceDomain\Money\Decimal;

if (! function_exists('as_money')) {
    function as_money(string | int | null $value): string
    {
        $amount = Decimal::normalize($value ?? '0.00');

        return (Decimal::compare($amount, '0.00') < 0 ? '-' : '') . '$ ' . Decimal::format(Decimal::abs($amount));
    }
}
