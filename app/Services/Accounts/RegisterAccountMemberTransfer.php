<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterAccountMemberTransfer
{
    public function execute(Account $account, array $payload): array
    {
        $amount = round((float) $payload['amount'], 2);
        $occurredAt = isset($payload['occurred_at'])
            ? Carbon::parse($payload['occurred_at'])
            : Carbon::now();
        $description = $payload['description'] ?? 'Transferencia interna';

        return DB::transaction(function () use ($account, $payload, $amount, $occurredAt, $description): array {
            $custodyFromEntry = $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['from_user_id'],
                'related_user_id' => (int) $payload['to_user_id'],
                'type' => AccountMemberLedgerEntryType::InternalTransfer,
                'amount' => $amount * -1,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            $custodyToEntry = $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['to_user_id'],
                'related_user_id' => (int) $payload['from_user_id'],
                'type' => AccountMemberLedgerEntryType::InternalTransfer,
                'amount' => $amount,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            $settlementFromEntry = $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['from_user_id'],
                'related_user_id' => (int) $payload['to_user_id'],
                'type' => AccountMemberLedgerEntryType::SettlementTransfer,
                'amount' => $amount,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            $settlementToEntry = $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['to_user_id'],
                'related_user_id' => (int) $payload['from_user_id'],
                'type' => AccountMemberLedgerEntryType::SettlementTransfer,
                'amount' => $amount * -1,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            return [$custodyFromEntry, $custodyToEntry, $settlementFromEntry, $settlementToEntry];
        });
    }
}
