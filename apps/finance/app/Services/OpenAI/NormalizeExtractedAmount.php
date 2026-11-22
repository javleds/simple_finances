<?php

declare(strict_types=1);

namespace App\Services\OpenAI;

use FinSi\FinanceDomain\Money\Decimal;
use InvalidArgumentException;

use function is_string;
use function preg_match;
use function strlen;

final class NormalizeExtractedAmount
{
    public function execute(mixed $amount): ?string
    {
        if (null === $amount) {
            return null;
        }
        if (! is_string($amount) || strlen($amount) > 11 || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException('An extracted amount must be a decimal string with at most two decimal places.');
        }
        if (Decimal::compare($amount, '0.00') <= 0) {
            throw new InvalidArgumentException('An extracted transaction amount must be positive.');
        }

        return Decimal::normalize($amount);
    }
}
