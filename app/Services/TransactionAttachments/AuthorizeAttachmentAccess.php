<?php

declare(strict_types=1);

namespace App\Services\TransactionAttachments;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Services\Api\AuthorizeAccountAccess;

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
        if ($attachment !== null) {
            abort_unless($attachment->transaction_id === $transaction->id && $attachment->deleted_at === null, 404);
        }
    }
}
