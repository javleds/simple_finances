<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class BuildPendingReimbursementItems
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $accountId, int $fromUserId, int $toUserId, float $totalAmount): array
    {
        $items = AccountMemberLedgerEntry::query()
            ->selectRaw('transaction_id, round(sum(amount), 2) as open_amount')
            ->where('account_id', $accountId)
            ->where('user_id', $fromUserId)
            ->whereNotNull('transaction_id')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
            ])
            ->groupBy('transaction_id')
            ->havingRaw('open_amount < -0.001')
            ->orderByDesc('transaction_id')
            ->with('transaction')
            ->get()
            ->map(fn (AccountMemberLedgerEntry $entry): array => $this->item($entry))
            ->filter(fn (array $item): bool => $item['amount'] > 0.0)
            ->values();

        return $this->fitItemsToTotal($items, round($totalAmount, 2));
    }

    private function item(AccountMemberLedgerEntry $entry): array
    {
        /** @var Transaction|null $transaction */
        $transaction = $entry->transaction;

        return [
            'transaction_id' => (string) $entry->transaction_id,
            'concept' => $transaction?->concept ?? 'Movimiento no disponible',
            'amount' => round(abs((float) $entry->open_amount), 2),
            'occurred_at' => optional($transaction?->scheduled_at)->toDateString(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function fitItemsToTotal(Collection $items, float $totalAmount): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $runningTotal = 0.0;
        $fittedItems = $items
            ->map(function (array $item) use (&$runningTotal, $totalAmount): array {
                $remaining = round($totalAmount - $runningTotal, 2);

                if ($remaining <= 0.0) {
                    return [...$item, 'amount' => 0.0];
                }

                $amount = round(min((float) $item['amount'], $remaining), 2);
                $runningTotal = round($runningTotal + $amount, 2);

                return [...$item, 'amount' => $amount];
            })
            ->filter(fn (array $item): bool => $item['amount'] > 0.0)
            ->values();

        $delta = round($totalAmount - $fittedItems->sum('amount'), 2);

        if ($delta !== 0.0 && $fittedItems->isNotEmpty()) {
            $lastIndex = $fittedItems->keys()->last();
            $lastItem = $fittedItems[$lastIndex];
            $fittedItems[$lastIndex] = [
                ...$lastItem,
                'amount' => round((float) $lastItem['amount'] + $delta, 2),
            ];
        }

        return $fittedItems->all();
    }
}
