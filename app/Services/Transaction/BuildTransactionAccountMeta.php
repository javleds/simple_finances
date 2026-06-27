<?php

namespace App\Services\Transaction;

use App\Dto\AccountBalanceMetaDto;
use App\Models\Account;
use App\Services\Accounts\BuildPendingIncomeByUser;

class BuildTransactionAccountMeta
{
    public function __construct(private readonly BuildPendingIncomeByUser $buildPendingIncomeByUser) {}

    public function execute(
        int $accountId,
        ?int $previousAccountId = null,
        bool $includePendingByUser = false,
    ): array
    {
        $meta = [
            'account' => $this->accountMeta($accountId)->toArray(),
        ];

        if ($includePendingByUser) {
            $meta['pending_by_user'] = $this->pendingByUserMeta($accountId);
        }

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

    private function pendingByUserMeta(int $accountId): array
    {
        $account = Account::withoutGlobalScopes()->findOrFail($accountId);

        return $this->buildPendingIncomeByUser->execute($account);
    }
}
