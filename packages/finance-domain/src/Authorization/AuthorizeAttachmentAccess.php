<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use function abort_if;
use function abort_unless;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\TransactionAttachment;

class AuthorizeAttachmentAccess
{
    public function __construct(private readonly AuthorizeAccountAccess $accounts)
    {
    }

    public function execute(Transaction $transaction, int $userId, bool $manage = false, ?TransactionAttachment $attachment = null): void
    {
        $account = Account::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($transaction->account_id);
        $this->accounts->ensureMember($account, $userId);
        abort_if($manage && $transaction->user_id !== $userId, 403);
        if (null !== $attachment) {
            abort_unless($attachment->transaction_id === $transaction->id && null === $attachment->deleted_at, 404);
        }
    }
}
