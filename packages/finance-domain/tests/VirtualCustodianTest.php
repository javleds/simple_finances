<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Tests;

use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Queries\EligiblePersonalMoneyAccounts;
use FinSi\FinanceDomain\Queries\VisibleAccountsForUser;
use FinSi\FinanceDomain\ReadModels\BuildAccountMemberSummary;

use function now;

final class VirtualCustodianTest extends TestCase
{
    public function testFinancialCustodyDoesNotConferMembershipOrVisibility(): void
    {
        $owner = new User();
        $owner->forceFill(['name' => 'Owner', 'email' => 'owner@example.com'])->save();
        $account = new Account();
        $account->forceFill(['name' => 'Shared', 'user_id' => $owner->id])->save();
        $account->users()->attach($owner);
        self::assertFalse($account->usesSharedAccountRules());
        self::assertTrue($this->app->make(EligiblePersonalMoneyAccounts::class)->execute($owner->id)->whereKey($account->id)->exists());
        $custodian = new User();
        $custodian->forceFill(['name' => 'Deposit', 'email' => 'deposit@custodians.invalid', 'virtual_account_id' => $account->id])->save();
        self::assertTrue($account->usesSharedAccountRules());
        self::assertFalse($this->app->make(EligiblePersonalMoneyAccounts::class)->execute($owner->id)->whereKey($account->id)->exists());
        $account->memberLedgerEntries()->create([
            'user_id' => $custodian->id,
            'type'    => AccountMemberLedgerEntryType::IncomeCustody,
            'amount'  => 1000,
        ]);

        $summary = $this->app->make(BuildAccountMemberSummary::class)->execute($account);
        self::assertSame([$owner->id], $account->users()->pluck('users.id')->all());
        self::assertSame([], $this->app->make(VisibleAccountsForUser::class)->query($custodian->id)->pluck('id')->all());
        self::assertSame($custodian->id, $summary['virtual_custodians'][0]['id']);
        self::assertSame('1000.00', $summary['virtual_custodians'][0]['custody_amount']);

        $custodian->virtual_retired_at = now();
        $custodian->save();
        self::assertFalse($account->usesSharedAccountRules());
        self::assertTrue($this->app->make(EligiblePersonalMoneyAccounts::class)->execute($owner->id)->whereKey($account->id)->exists());
        $retired = $this->app->make(BuildAccountMemberSummary::class)->execute($account);
        self::assertSame([], $retired['virtual_custodians']);
        self::assertSame('1000.00', collect($retired['custody_by_user'])->firstWhere('user_id', $custodian->id)['amount']);
        $member = new User();
        $member->forceFill(['name' => 'Member', 'email' => 'member@example.com'])->save();
        $account->users()->attach($member);
        self::assertTrue($account->usesSharedAccountRules());
    }
}
