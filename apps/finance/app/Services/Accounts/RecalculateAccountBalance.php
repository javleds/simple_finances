<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Dto\AccountBalanceSnapshotDto;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Validation\ValidationException;

class RecalculateAccountBalance
{
    public function execute(Account $account): AccountBalanceSnapshotDto
    {
        if (! $account->credit_card) {
            return $this->recalculateStandardAccount($account);
        }

        return $this->recalculateCreditCard($account);
    }

    private function recalculateStandardAccount(Account $account): AccountBalanceSnapshotDto
    {
        $balance = Decimal::subtract($this->completedIncome($account), $this->completedOutcome($account));

        $this->validateStoredAmount($balance);
        $account->balance = $balance;
        $account->save();

        return new AccountBalanceSnapshotDto(balance: Decimal::normalize($account->balance));
    }

    private function recalculateCreditCard(Account $account): AccountBalanceSnapshotDto
    {
        $spent = Decimal::subtract($this->completedIncome($account), $this->completedOutcome($account));
        $availableCredit = Decimal::add($account->credit_line ?? '0.00', $spent);
        $balance = Decimal::subtract($this->completedIncome($account, untilCutoff: true), $this->completedOutcome($account, untilCutoff: true));

        $this->validateStoredAmount($balance);
        $this->validateStoredAmount($availableCredit);
        $account->spent = $spent;
        $account->available_credit = $availableCredit;
        $account->balance = $balance;
        $account->save();

        return new AccountBalanceSnapshotDto(
            balance: Decimal::normalize($account->balance),
            spent: Decimal::normalize($account->spent),
            availableCredit: Decimal::normalize($account->available_credit),
        );
    }

    private function completedIncome(Account $account, bool $untilCutoff = false): string
    {
        $query = $account->transactions()
            ->withoutGlobalScopes()
            ->completed()
            ->income()
            ->where(function ($query): void {
                $query
                    ->whereNull('legacy_migrated_at')
                    ->orWhereNotNull('parent_transaction_id');
            });

        if ($untilCutoff) {
            $query->beforeOrEqualsTo($account->next_cutoff_date);
        }

        return Decimal::normalize($query->sum('amount'));
    }

    private function completedOutcome(Account $account, bool $untilCutoff = false): string
    {
        $query = $account->transactions()
            ->withoutGlobalScopes()
            ->whereNull('legacy_migrated_at')
            ->completed()
            ->outcome();

        if ($untilCutoff) {
            $query->beforeOrEqualsTo($account->next_cutoff_date);
        }

        return Decimal::normalize($query->sum('amount'));
    }

    private function validateStoredAmount(string $amount): void
    {
        if (Decimal::compare(Decimal::abs($amount), '99999999.99') > 0) {
            throw ValidationException::withMessages(['amount' => 'The resulting account balance exceeds the supported monetary range.']);
        }
    }
}
