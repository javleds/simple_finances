<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Money;

use Brick\Money\Money;
use InvalidArgumentException;

final class CreateMoney
{
    public function execute(mixed $amount): Money
    {
        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('Money requires a decimal string or an integer; floats are not accepted.');
        }

        $value = (string) $amount;

        if (strlen($value) > 128 || ! preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Money requires a decimal amount with at most two fraction digits.');
        }

        return Money::of($value, 'MXN');
    }
}
