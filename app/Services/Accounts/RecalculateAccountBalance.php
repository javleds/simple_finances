<?php

namespace App\Services\Accounts;

use App\Dto\AccountBalanceSnapshotDto;
use App\Models\Account;

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
        $balance = $this->completedIncome($account) - $this->completedOutcome($account);

        $account->balance = $balance;
        $account->save();

        return new AccountBalanceSnapshotDto(balance: (float) $account->balance);
    }

    private function recalculateCreditCard(Account $account): AccountBalanceSnapshotDto
    {
        $spent = $this->completedIncome($account) - $this->completedOutcome($account);
        $availableCredit = $account->credit_line - ($spent * -1);
        $balance = $this->completedIncome($account, untilCutoff: true)
            - $this->completedOutcome($account, untilCutoff: true);

        $account->spent = $spent;
        $account->available_credit = $availableCredit;
        $account->balance = $balance;
        $account->save();

        return new AccountBalanceSnapshotDto(
            balance: (float) $account->balance,
            spent: (float) $account->spent,
            availableCredit: (float) $account->available_credit,
        );
    }

    private function completedIncome(Account $account, bool $untilCutoff = false): float
    {
        $query = $account->transactions()->withoutGlobalScopes()->completed()->income();

        if ($untilCutoff) {
            $query->beforeOrEqualsTo($account->next_cutoff_date);
        }

        return (float) $query->sum('amount');
    }

    private function completedOutcome(Account $account, bool $untilCutoff = false): float
    {
        $query = $account->transactions()->withoutGlobalScopes()->completed()->outcome();

        if ($untilCutoff) {
            $query->beforeOrEqualsTo($account->next_cutoff_date);
        }

        return (float) $query->sum('amount');
    }
}
