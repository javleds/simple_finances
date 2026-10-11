<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\PersonalMoneyReimbursement;

use function in_array;
use function round;

class CapturePersonalMoneyReimbursements
{
    public function execute(Account $account, array $payload, array $entries): void
    {
        $action = $payload['action_type'] ?? 'user_to_user';
        if ('user_to_account' === $action) {
            return;
        }

        $fromUserId = (int) $payload['from_user_id'];
        $toUserId = (int) $payload['to_user_id'];
        $amount = round((float) $payload['amount'], 2);
        $remaining = [];
        if ('user_to_user' === $action && $fromUserId !== $toUserId) {
            $remaining[$fromUserId] = -$amount;
            $remaining[$toUserId] = $amount;
        }
        if ('custody_to_user' === $action) {
            $remaining[$toUserId] = $amount;
        }

        foreach ($entries as $entry) {
            if (! $entry instanceof AccountMemberLedgerEntry || ! in_array($entry->type, [
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            ], true)) {
                continue;
            }

            $effect = $remaining[$entry->user_id] ?? 0.0;
            PersonalMoneyReimbursement::query()->firstOrCreate(['ledger_entry_key' => $entry->id], [
                'user_id'      => $entry->user_id, 'account_id' => $account->id, 'cash_effect' => $effect,
                'account_name' => $account->name,
                'description'  => $entry->description ?? 'Reimbursement', 'occurred_at' => $entry->occurred_at,
            ]);
            unset($remaining[$entry->user_id]);
        }
    }
}
