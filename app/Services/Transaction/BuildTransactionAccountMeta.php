<?php

namespace App\Services\Transaction;

use App\Dto\AccountBalanceMetaDto;
use App\Models\Account;
use App\Services\Accounts\BuildAccountLedgerTimeline;
use App\Services\Accounts\BuildAccountMemberSummary;

class BuildTransactionAccountMeta
{
    public function __construct(
        private readonly BuildAccountMemberSummary $buildAccountMemberSummary,
        private readonly BuildAccountLedgerTimeline $buildAccountLedgerTimeline,
    ) {}

    public function execute(
        int $accountId,
        ?int $previousAccountId = null,
    ): array
    {
        $meta = [
            'account' => $this->accountMeta($accountId)->toArray(),
            'ledger_rows' => $this->ledgerRows($accountId),
        ];

        $meta = array_merge($meta, $this->memberSummaryMeta($accountId));

        if ($previousAccountId && $previousAccountId !== $accountId) {
            $meta['previous_account'] = $this->accountMeta($previousAccountId)->toArray();
        }

        return $meta;
    }

    private function accountMeta(int $accountId): AccountBalanceMetaDto
    {
        $account = Account::withoutGlobalScopes()->findOrFail($accountId);

        return new AccountBalanceMetaDto(
            id: $account->id,
            balance: (float) $account->balance,
        );
    }

    private function memberSummaryMeta(int $accountId): array
    {
        $account = Account::withoutGlobalScopes()->findOrFail($accountId);

        return $this->buildAccountMemberSummary->execute($account);
    }

    private function ledgerRows(int $accountId): array
    {
        $account = Account::withoutGlobalScopes()->findOrFail($accountId);

        return $this->buildAccountLedgerTimeline->execute($account)
            ->take(20)
            ->values()
            ->all();
    }
}
