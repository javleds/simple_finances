<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\DB;

class RenameAccountCustodian
{
    public function execute(Account $account, User $custodian, string $name): void
    {
        DB::transaction(function () use ($account, $custodian, $name): void {
            $account = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $custodian = $account->virtualCustodians()->whereNull('virtual_retired_at')->whereKey($custodian->id)->lockForUpdate()->firstOrFail();
            $custodian->name = $name;
            $custodian->save();
        });
    }
}
