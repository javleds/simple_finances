<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Decimal
{
    public static function normalize(mixed $value, int $scale = 2): string
    {
        return (string) self::number($value)->toScale($scale);
    }

    public static function round(mixed $value, int $scale = 2): string
    {
        return (string) self::number($value)->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function add(mixed $left, mixed $right): string
    {
        return (string) (new CreateMoney())->execute($left)->plus((new CreateMoney())->execute($right))->getAmount();
    }

    public static function subtract(mixed $left, mixed $right): string
    {
        return (string) (new CreateMoney())->execute($left)->minus((new CreateMoney())->execute($right))->getAmount();
    }

    public static function multiply(mixed $left, mixed $right, int $scale = 2): string
    {
        return (string) self::number($left)->multipliedBy(self::number($right))->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function divide(mixed $left, mixed $right, int $scale = 2): string
    {
        return (string) self::number($left)->dividedBy(self::number($right), $scale, RoundingMode::HALF_UP);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return self::number($left)->compareTo(self::number($right));
    }

    public static function ratio(mixed $amount, mixed $numerator, mixed $denominator, int $scale = 2): string
    {
        return (string) self::number($amount)->toBigRational()
            ->multipliedBy(self::number($numerator))
            ->dividedBy(self::number($denominator))
            ->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function minor(mixed $amount): int
    {
        return (new CreateMoney())->execute($amount)->getMinorAmount()->toInt();
    }

    public static function fromMinor(mixed $amount): string
    {
        return (string) self::number($amount)->toBigInteger()->toBigDecimal()->dividedBy(100, 2);
    }

    public static function min(mixed ...$values): string
    {
        return self::normalize(BigDecimal::min(...array_map(self::number(...), $values))->__toString());
    }

    public static function max(mixed ...$values): string
    {
        return self::normalize(BigDecimal::max(...array_map(self::number(...), $values))->__toString());
    }

    public static function abs(mixed $value): string
    {
        return (string) (new CreateMoney())->execute($value)->abs()->getAmount();
    }

    public static function negate(mixed $value): string
    {
        return (string) (new CreateMoney())->execute($value)->negated()->getAmount();
    }

    public static function sum(iterable $values, ?string $field = null): string
    {
        $total = (new CreateMoney())->execute('0.00');

        foreach ($values as $value) {
            $amount = $field === null ? $value : (is_array($value) ? $value[$field] : $value->{$field});
            $total = $total->plus((new CreateMoney())->execute($amount));
        }

        return (string) $total->getAmount();
    }

    public static function format(mixed $value): string
    {
        $canonical = self::normalize($value);
        [$integer, $fraction] = explode('.', $canonical);

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer).'.'.$fraction;
    }

    private static function number(mixed $value): BigDecimal
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('A decimal string or integer is required; floats are not accepted.');
        }

        $decimal = (string) $value;

        if (strlen($decimal) > 128 || ! preg_match('/^-?\d+(?:\.\d+)?$/D', $decimal)) {
            throw new InvalidArgumentException('A plain decimal string is required.');
        }

        return BigDecimal::of($decimal);
    }
}
