<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\ReadModels;

use FinSi\FinanceDomain\Money\Decimal;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Support\Collection;

use function optional;

class BuildPendingReimbursementItems
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $accountId, int $fromUserId, int $toUserId, string $totalAmount): array
    {
        $items = AccountMemberLedgerEntry::query()
            ->selectRaw('transaction_id, sum(amount) as open_amount')
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
            ->havingRaw('open_amount < 0')
            ->orderByDesc('transaction_id')
            ->with('transaction')
            ->get()
            ->map(fn (AccountMemberLedgerEntry $entry): array => $this->item($entry))
            ->filter(fn (array $item): bool => Decimal::compare($item['amount'], 0) > 0)
            ->values();

        return $this->fitItemsToTotal($items, Decimal::normalize($totalAmount));
    }

    private function item(AccountMemberLedgerEntry $entry): array
    {
        /** @var Transaction|null $transaction */
        $transaction = $entry->transaction;

        return [
            'transaction_id' => (string) $entry->transaction_id,
            'attribution'    => 'estimated',
            'concept'        => $transaction?->concept ?? 'Movimiento no disponible',
            'amount'         => Decimal::abs((string) $entry->open_amount),
            'occurred_at'    => optional($transaction?->scheduled_at)->toDateString(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function fitItemsToTotal(Collection $items, string $totalAmount): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $runningTotal = '0.00';
        $fittedItems = $items
            ->map(function (array $item) use (&$runningTotal, $totalAmount): array {
                $remaining = Decimal::subtract($totalAmount, $runningTotal);

                if (Decimal::compare($remaining, 0) <= 0) {
                    return [...$item, 'amount' => '0.00'];
                }

                $amount = Decimal::min($item['amount'], $remaining);
                $runningTotal = Decimal::add($runningTotal, $amount);

                return [...$item, 'amount' => $amount];
            })
            ->filter(fn (array $item): bool => Decimal::compare($item['amount'], 0) > 0)
            ->values();

        $delta = Decimal::subtract($totalAmount, Decimal::sum($fittedItems, 'amount'));

        if (Decimal::compare($delta, 0) !== 0 && $fittedItems->isNotEmpty()) {
            $lastIndex = $fittedItems->keys()->last();
            $lastItem = $fittedItems[$lastIndex];
            $fittedItems[$lastIndex] = [
                ...$lastItem,
                'amount' => Decimal::add($lastItem['amount'], $delta),
            ];
        }

        return $fittedItems->all();
    }
}
