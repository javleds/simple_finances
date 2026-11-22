<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Tests;

use Brick\Math\Exception\RoundingNecessaryException;
use FinSi\FinanceDomain\Models\AccountLedgerRepair;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMember;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\SharedTransactionNotificationItem;
use FinSi\FinanceDomain\Money\CreateMoney;
use FinSi\FinanceDomain\Money\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExactMoneyTest extends TestCase
{
    public function testMoneyPreservesAmountsBeyondBinaryIntegerPrecision(): void
    {
        $money = (new CreateMoney())->execute('900719925474099.91');

        self::assertSame('900719925474099.92', (string) $money->plus('0.01')->getAmount());
        self::assertSame('900719925474099.91', Decimal::subtract(Decimal::add('900719925474099.91', '0.01'), '0.01'));
        self::assertSame('0.30', Decimal::sum(['0.10', '0.20']));
    }

    public function testWeakCallersCannotSilentlyCoerceFloats(): void
    {
        $weakCaller = eval('return static fn ($value) => \FinSi\FinanceDomain\Money\Decimal::add($value, "0.01");');
        $this->expectException(InvalidArgumentException::class);
        $weakCaller(1.23);
    }

    public function testNormalizeRejectsFloatsBeforeConversion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::normalize(1.23);
    }

    public function testMoneyRejectsExcessPrecisionInsteadOfRounding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CreateMoney())->execute('1.001');
    }

    public function testMoneyRejectsExponentNotation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CreateMoney())->execute('1e3');
    }

    public function testNormalizeDoesNotRound(): void
    {
        $this->expectException(RoundingNecessaryException::class);
        Decimal::normalize('1.001');
    }

    public function testExactRatiosRoundOnlyAtTheFinalBoundary(): void
    {
        self::assertSame('33.33', Decimal::ratio('100.00', 1, 3));
        self::assertSame('33.34', Decimal::subtract('100.00', Decimal::sum(['33.33', '33.33'])));
        self::assertSame('1.01', Decimal::round('1.005'));
        self::assertSame('-1.01', Decimal::round('-1.005'));
        self::assertSame('0.01', Decimal::ratio('0.03', '16.67', 100));
        self::assertSame('-0.02', Decimal::subtract('0.03', Decimal::sum(array_fill(0, 5, '0.01'))));
    }

    public function testComparisonsAndSignsDoNotUseNativeFloatCoercion(): void
    {
        self::assertSame(1, Decimal::compare('900719925474099.92', '900719925474099.91'));
        self::assertSame('900719925474099.91', Decimal::min('900719925474099.92', '900719925474099.91'));
        self::assertSame('900719925474099.92', Decimal::max('900719925474099.92', '900719925474099.91'));
        self::assertSame('1.01', Decimal::abs('-1.01'));
        self::assertSame('-1.01', Decimal::negate('1.01'));
        self::assertSame('0.00', Decimal::normalize('-0.00'));
    }

    public function testMinorUnitsAndFormattingPreserveAllDigits(): void
    {
        self::assertSame(12345, Decimal::minor('123.45'));
        self::assertSame('-123.45', Decimal::fromMinor('-12345'));
        self::assertSame('900,719,925,474,099.91', Decimal::format('900719925474099.91'));
        self::assertSame('-1,234.56', Decimal::format('-1234.56'));
    }

    public function testLegacyRepairJsonPreservesNumericLexemesWithoutChangingDescriptionOrIds(): void
    {
        $repair = new AccountLedgerRepair();
        $repair->setRawAttributes([
            'input_payload' => '{"amount":900719925474099.91,"user_id":42,"description":"amount: 12.34","preview":{"amount":-0.01,"percentage":5e1}}',
        ]);

        self::assertSame('900719925474099.91', $repair->input_payload['amount']);
        self::assertSame(42, $repair->input_payload['user_id']);
        self::assertSame('amount: 12.34', $repair->input_payload['description']);
        self::assertSame('-0.01', $repair->input_payload['preview']['amount']);
        self::assertSame('50.00', $repair->input_payload['preview']['percentage']);
    }

    public function testRepairPayloadWritesRejectApproximateMoney(): void
    {
        $repair = new AccountLedgerRepair();
        $this->expectException(InvalidArgumentException::class);
        $repair->input_payload = ['amount' => 1.23];
    }

    public function testMigrationColumnsAreNeverExposedInSerializedModels(): void
    {
        $account = new Account();
        $account->spent = '123.45';
        $account->spent_legacy = '123.45001';
        $account->spent_exact = '123.45';
        $notification = new SharedTransactionNotificationItem();
        $notification->amount = '123.45';
        $notification->amount_legacy = '123.45001';
        $notification->amount_exact = '123.45';

        self::assertArrayNotHasKey('spent_legacy', $account->toArray());
        self::assertArrayNotHasKey('spent_exact', $account->toArray());
        self::assertArrayNotHasKey('amount_legacy', $notification->toArray());
        self::assertArrayNotHasKey('amount_exact', $notification->toArray());
        self::assertSame('123.45', $notification->toArray()['amount']);
    }

    public function testSerializationKeepsAmountsAndPercentagesAsStrings(): void
    {
        $transaction = new Transaction();
        $transaction->amount = '123.45';
        $transaction->percentage = '33.33';
        $account = new Account();
        $account->balance = '900719925474099.91';
        $account->credit_line = null;
        $member = new AccountMember();
        $member->percentage = '33.33';

        self::assertSame('123.45', $transaction->toArray()['amount']);
        self::assertSame('33.33', $transaction->toArray()['percentage']);
        self::assertSame('900719925474099.91', $account->toArray()['balance']);
        self::assertNull($account->toArray()['credit_line']);
        self::assertSame('33.33', $member->toArray()['percentage']);
    }
}
