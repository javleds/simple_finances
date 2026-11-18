<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use function abs;
use function collect;

use FinSi\FinanceDomain\Models\Account;

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
            $custody = collect($summary['custody_by_user'])->firstWhere('user_id', $custodian->id)['amount'] ?? 0.0;
            $pending = collect($summary['pending_reimbursements'])->contains(fn (array $item): bool => (int) $item['from_user_id'] === $custodian->id || (int) $item['to_user_id'] === $custodian->id);
            if (abs($custody) >= 0.005 || $pending) {
                $custodian->virtual_retired_at = null;
                $custodian->save();
            }
        }
    }
}
