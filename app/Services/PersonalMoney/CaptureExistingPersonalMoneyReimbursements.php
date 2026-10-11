<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use function abs;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\AccountMemberLedgerEntry;
use App\Models\PersonalMoneyReimbursement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;

class CaptureExistingPersonalMoneyReimbursements
{
    public function execute(int $userId, string $startedAt): void
    {
        AccountMemberLedgerEntry::query()->where('user_id', $userId)
            ->where('occurred_at', '>=', $startedAt)->whereIn('type', [
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            ])->whereNotExists(static function (Builder $query): void {
                $query->selectRaw('1')->from('personal_money_reimbursements')
                    ->whereColumn('personal_money_reimbursements.ledger_entry_key', 'account_member_ledger_entries.id');
            })->with('account')->orderBy('id')->chunkById(200, function (Collection $entries): void {
                foreach ($entries as $entry) {
                    $effect = $this->cashEffect($entry);
                    PersonalMoneyReimbursement::query()->firstOrCreate(['ledger_entry_key' => $entry->id], [
                        'user_id'     => $entry->user_id, 'account_id' => $entry->account_id,
                        'cash_effect' => $effect, 'account_name' => $entry->account?->name ?? 'Previous account', 'description' => $entry->description ?? 'Reimbursement',
                        'occurred_at' => $entry->occurred_at,
                    ]);
                }
            });
    }

    private function cashEffect(AccountMemberLedgerEntry $entry): float
    {
        if (AccountMemberLedgerEntryType::CustodyReimbursementPayment === $entry->type) {
            return $entry->user_id === $entry->related_user_id ? abs($entry->amount) : 0.0;
        }
        if (null === $entry->related_user_id || $entry->user_id === $entry->related_user_id) {
            return 0.0;
        }

        return -$entry->amount;
    }
}
