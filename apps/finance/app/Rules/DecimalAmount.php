<?php

declare(strict_types=1);

namespace App\Rules;

use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use function is_string;
use function preg_match;
use function strlen;

final class DecimalAmount implements ValidationRule
{
    public function __construct(
        private readonly string $minimum = '0.00',
        private readonly string $maximum = '99999999.99',
        private readonly bool $exclusiveMinimum = false,
        private readonly bool $nonzero = false,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 24 || ! preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value)) {
            $fail('The :attribute must be a decimal string with at most two decimal places.');

            return;
        }

        $amount = BigDecimal::of($value);
        $minimumComparison = $amount->compareTo($this->minimum);
        if ($minimumComparison < 0 || ($this->exclusiveMinimum && 0 === $minimumComparison) || $amount->isGreaterThan($this->maximum)) {
            $fail('The :attribute is outside the permitted monetary range.');

            return;
        }

        if ($this->nonzero && $amount->isZero()) {
            $fail('The :attribute must not be zero.');
        }
    }
}
