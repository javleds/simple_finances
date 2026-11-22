<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\PersonalMoneyReimbursement;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;

use function in_array;

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
        $amount = Decimal::normalize($payload['amount']);
        $remaining = [];
        if ('user_to_user' === $action && $fromUserId !== $toUserId) {
            $remaining[$fromUserId] = Decimal::negate($amount);
            $remaining[$toUserId] = $amount;
        }
        if ('custody_to_user' === $action) {
            $remaining[$toUserId] = $amount;
        }

        foreach ($entries as $entry) {
            if (! $entry instanceof AccountMemberLedgerEntry || ! in_array($entry->type, [
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
                AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            ], true)) {
                continue;
            }

            if (User::query()->find($entry->user_id)?->isVirtualCustodian()) {
                continue;
            }

            $effect = $remaining[$entry->user_id] ?? '0.00';
            PersonalMoneyReimbursement::query()->firstOrCreate(['ledger_entry_key' => $entry->id], [
                'user_id'      => $entry->user_id, 'account_id' => $account->id, 'cash_effect' => $effect,
                'account_name' => $account->name,
                'description'  => $entry->description ?? 'Reimbursement', 'occurred_at' => $entry->occurred_at,
            ]);
            unset($remaining[$entry->user_id]);
        }
    }
}
