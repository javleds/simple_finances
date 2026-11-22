<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use function collect;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Money\Decimal;

class ReopenVirtualCustodians
{
    public function __construct(private readonly BuildAccountMemberSummary $buildAccountMemberSummary)
    {
    }

    public function execute(Account $account): void
    {
        $retired = $account->virtualCustodians()->whereNotNull('virtual_retired_at')->get();
        if ($retired->isEmpty()) {
            return;
        }

        $summary = $this->buildAccountMemberSummary->execute($account);
        foreach ($retired as $custodian) {
            $custody = collect($summary['custody_by_user'])->firstWhere('user_id', $custodian->id)['amount'] ?? '0.00';
            $pending = collect($summary['pending_reimbursements'])->contains(fn (array $item): bool => (int) $item['from_user_id'] === $custodian->id || (int) $item['to_user_id'] === $custodian->id);
            if (0 !== Decimal::compare($custody, 0) || $pending) {
                $custodian->virtual_retired_at = null;
                $custodian->save();
            }
        }
    }
}
