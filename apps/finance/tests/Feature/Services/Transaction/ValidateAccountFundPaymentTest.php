<?php

declare(strict_types=1);

use App\Services\Transaction\ValidateAccountFundPayment;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Validation\ValidationException;

it('rejects new shared account fund payments without positive funds', function (string $balance): void {
    $account = new Account(['balance' => $balance]);
    $transaction = new Transaction(['account_id' => 1, 'type' => 'outcome', 'uses_virtual_custodian_rules' => true, 'uses_shared_account_rules' => true, 'payment_source' => 'account_fund']);
    expect(fn () => app(ValidateAccountFundPayment::class)->execute($account, $transaction))->toThrow(ValidationException::class);
})->with(['0.00', '-20.00']);

it('allows individual or out of pocket payments and shared payments with funds', function (bool $shared, string $source, string $balance): void {
    $account = new Account(['balance' => $balance]);
    $transaction = new Transaction(['account_id' => 1, 'type' => 'outcome', 'uses_virtual_custodian_rules' => true, 'uses_shared_account_rules' => $shared, 'payment_source' => $source]);
    app(ValidateAccountFundPayment::class)->execute($account, $transaction);
    expect($transaction->payment_source->value)->toBe($source);
})->with([[false, 'account_fund', '0.00'], [true, 'member_out_of_pocket', '0.00'], [true, 'account_fund', '10.00']]);

it('preserves historical payments but validates a new destination', function (): void {
    $account = new Account(['balance' => 0]);
    $previous = new Transaction(['account_id' => 1, 'type' => 'outcome', 'uses_virtual_custodian_rules' => true, 'uses_shared_account_rules' => true, 'payment_source' => 'account_fund']);
    $transaction = clone $previous;
    app(ValidateAccountFundPayment::class)->execute($account, $transaction, $previous);
    $transaction->account_id = 2;
    expect(fn () => app(ValidateAccountFundPayment::class)->execute($account, $transaction, $previous))->toThrow(ValidationException::class);
});
