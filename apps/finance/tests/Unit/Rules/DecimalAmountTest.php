<?php

declare(strict_types=1);

use App\Rules\DecimalAmount;

it('accepts only exact decimal strings within the declared bounds', function (mixed $value, bool $valid): void {
    $errors = [];
    (new DecimalAmount(minimum: '-999999999999.99', maximum: '999999999999.99'))->validate('amount', $value, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });
    expect([] === $errors)->toBe($valid);
})->with([
    ['0', true], ['-0.00', true], ['123.4', true], ['999999999999.99', true],
    ['-999999999999.99', true], ['1000000000000.00', false], ['0.001', false],
    [0.1, false], [1, false], ['1e2', false], ['1,000.00', false], ['', false],
    ['NaN', false], ['Infinity', false], ['1.00\n', false], [null, false],
]);

it('checks strict positivity and nonzero using decimals', function (): void {
    $errors = [];
    $fail = function (string $message) use (&$errors): void { $errors[] = $message; };
    (new DecimalAmount(exclusiveMinimum: true))->validate('amount', '0.00', $fail);
    (new DecimalAmount(nonzero: true))->validate('amount', '-0.00', $fail);
    expect($errors)->toHaveCount(2);
});
