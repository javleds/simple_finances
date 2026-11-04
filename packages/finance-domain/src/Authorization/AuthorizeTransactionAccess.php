<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;

class AuthorizeTransactionAccess
{
    public function __construct(private readonly AuthorizeAccountAccess $accounts)
    {
    }

    public function isVisible(Transaction $transaction, int $userId): bool
    {
        $account = Account::withoutGlobalScopes()->find($transaction->account_id);

        return null !== $account && $this->accounts->isMember($account, $userId);
    }

    public function ensureVisible(Transaction $transaction, int $userId): void
    {
        $account = Account::withoutGlobalScopes()->findOrFail($transaction->account_id);
        $this->accounts->ensureMember($account, $userId);
    }
}
