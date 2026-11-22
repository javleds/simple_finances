<?php

declare(strict_types=1);

use App\Services\OpenAI\NormalizeExtractedAmount;

it('preserves exact extracted decimal strings', function (string $input, string $expected): void {
    expect((new NormalizeExtractedAmount())->execute($input))->toBe($expected);
})->with([
    ['0.01', '0.01'], ['123.4', '123.40'], ['99999999.99', '99999999.99'], ['100', '100.00'],
]);

it('rejects ambiguous or invalid extracted amounts', function (mixed $input): void {
    expect(fn () => (new NormalizeExtractedAmount())->execute($input))->toThrow(InvalidArgumentException::class);
})->with([100, 0.1, '0', '-1.00', '1.001', '1e3', '1,000.00', '100000000.00', '', 'NaN']);

it('preserves missing extracted amounts', function (): void {
    expect((new NormalizeExtractedAmount())->execute(null))->toBeNull();
});
