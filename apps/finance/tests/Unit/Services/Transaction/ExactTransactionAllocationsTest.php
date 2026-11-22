<?php

declare(strict_types=1);

use App\Dto\CreateAccountCustodianDto;
use App\Dto\RetireAccountCustodianDto;
use App\Dto\UserPaymentDto;
use App\Services\Transaction\BuildSplitTransactionAllocations;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

it('conserves every cent and preserves the last participant remainder', function (string $amount, array $percentages, array $expected): void {
    $payments = array_map(fn (string $percentage, int $index): UserPaymentDto => new UserPaymentDto($index + 1, $percentage), $percentages, array_keys($percentages));
    $allocations = (new BuildSplitTransactionAllocations())->execute($amount, $payments);

    expect(array_column($allocations, 'amount'))->toBe($expected)
        ->and(Decimal::sum($allocations, 'amount'))->toBe($amount);
})->with([
    ['100.00', ['33.33', '33.33', '33.34'], ['33.33', '33.33', '33.34']],
    ['0.01', ['50.00', '50.00'], ['0.01', '0.00']],
    ['99999999.99', ['33.33', '33.33', '33.34'], ['33330000.00', '33330000.00', '33339999.99']],
]);

it('rejects a negative rounding remainder instead of persisting a negative share', function (): void {
    $payments = array_map(fn (int $id): UserPaymentDto => new UserPaymentDto($id, 6 === $id ? '16.65' : '16.67'), range(1, 6));

    expect(fn () => (new BuildSplitTransactionAllocations())->execute('0.03', $payments))
        ->toThrow(ValidationException::class);
});

it('ignores zero shares while preserving explicit participant order', function (): void {
    $allocations = (new BuildSplitTransactionAllocations())->execute('0.01', [
        new UserPaymentDto(9, '50.00'),
        new UserPaymentDto(7, '0.00'),
        new UserPaymentDto(2, '50.00'),
    ]);

    expect(array_column($allocations, 'userId'))->toBe([9, 2])
        ->and(array_column($allocations, 'amount'))->toBe(['0.01', '0.00']);
});

it('converts custody input to integer cents without a floating point intermediate', function (): void {
    $created = CreateAccountCustodianDto::fromArray([
        'name'            => 'Reserve',
        'opening_balance' => '99999999.99',
        'transfers'       => [['user_id' => 1, 'amount' => '0.29']],
    ]);
    $retired = RetireAccountCustodianDto::fromArray([
        'distributions' => [['user_id' => 1, 'amount' => '9999999999.99']],
    ]);

    expect($created->openingBalanceCents)->toBe(9999999999)
        ->and($created->transfers[0]['amount_cents'])->toBe(29)
        ->and($retired->distributions[0]['amount_cents'])->toBe(999999999999);
});
