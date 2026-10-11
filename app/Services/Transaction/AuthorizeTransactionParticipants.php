<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Models\Account;
use App\Models\Transaction;

class AuthorizeTransactionParticipants
{
    public function execute(Account $account, array $payload, ?Transaction $transaction = null): void
    {
        $memberIds = $account->users()->withoutGlobalScopes()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
        $sameAccount = $transaction !== null && $transaction->account_id === $account->id;

        foreach (['paid_by_user_id', 'custodian_user_id'] as $field) {
            $userId = isset($payload[$field]) ? (int) $payload[$field] : null;
            if ($userId === null || in_array($userId, $memberIds, true)) {
                continue;
            }
            abort_unless($sameAccount && $transaction->{$field} === $userId, 404);
        }

        $historicalIds = $sameAccount
            ? $transaction->allocations()->pluck('user_id')->map(fn ($id): int => (int) $id)->all()
            : [];

        foreach ($payload['user_payments'] ?? [] as $payment) {
            $userId = (int) $payment['user_id'];
            abort_unless(in_array($userId, $memberIds, true) || in_array($userId, $historicalIds, true), 404);
        }
    }
}
