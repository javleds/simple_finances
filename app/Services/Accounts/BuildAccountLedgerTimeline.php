<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildAccountLedgerTimeline
{
    /** @var array<int, string> */
    private array $userNames = [];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Account $account): Collection
    {
        $transactions = $account->transactions()
            ->withoutGlobalScopes()
            ->with([
                'user',
                'paidByUser',
                'custodianUser',
                'allocations.user',
                'ledgerEntries.user',
                'ledgerEntries.relatedUser',
                'subTransactions',
            ])
            ->completed()
            ->whereNull('legacy_migrated_at')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $entries = $account->memberLedgerEntries()
            ->with(['user', 'relatedUser', 'transaction'])
            ->where(function ($query): void {
                $query
                    ->whereNull('transaction_id')
                    ->orWhereIn('type', [
                        AccountMemberLedgerEntryType::SettlementCorrection,
                        AccountMemberLedgerEntryType::CustodyCorrection,
                    ]);
            })
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $events = $transactions
            ->toBase()
            ->map(fn (Transaction $transaction): array => [
                'sort_date' => $transaction->scheduled_at,
                'sort_id' => $transaction->id,
                'kind' => 'transaction',
                'payload' => $transaction,
            ])
            ->merge($entries->map(fn (AccountMemberLedgerEntry $entry): array => [
                'sort_date' => $entry->occurred_at,
                'sort_id' => $entry->id,
                'kind' => 'ledger_entry',
                'payload' => $entry,
            ]))
            ->sortBy([
                ['sort_date', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values();

        $balance = 0.0;
        $custodyByUser = [];
        $settlementByUser = [];

        return $events
            ->map(function (array $event) use (&$balance, &$custodyByUser, &$settlementByUser): array {
                if ($event['kind'] === 'transaction') {
                    return $this->transactionRow($event['payload'], $balance, $custodyByUser, $settlementByUser);
                }

                return $this->ledgerEntryRow($event['payload'], $balance, $custodyByUser, $settlementByUser);
            })
            ->reverse()
            ->values();
    }

    /**
     * @param array<int, float> $custodyByUser
     * @param array<int, float> $settlementByUser
     */
    private function transactionRow(
        Transaction $transaction,
        float &$balance,
        array &$custodyByUser,
        array &$settlementByUser,
    ): array {
        $balance = $transaction->type === TransactionType::Income
            ? round($balance + (float) $transaction->amount, 2)
            : round($balance - (float) $transaction->amount, 2);

        foreach ($transaction->subTransactions as $subTransaction) {
            if ($subTransaction->type === TransactionType::Income) {
                $balance = round($balance + (float) $subTransaction->amount, 2);
            }
        }

        foreach ($transaction->ledgerEntries->reject(fn (AccountMemberLedgerEntry $entry): bool => $this->isCorrectionEntry($entry)) as $entry) {
            $this->applyLedgerEntry($entry, $custodyByUser, $settlementByUser);
        }

        return [
            'id' => 'transaction-'.$transaction->id,
            'occurred_at' => optional($transaction->scheduled_at)->toDateString(),
            'source_type' => 'transaction',
            'transaction_id' => $transaction->id,
            'label' => $transaction->concept,
            'description' => $this->transactionDescription($transaction),
            'amount' => round((float) $transaction->amount, 2),
            'balance_after' => $balance,
            'custody_after_by_user' => $this->amountRows($custodyByUser),
            'settlement_after_by_user' => $this->amountRows($settlementByUser),
            'allocations' => $transaction->allocations
                ->map(fn ($allocation): array => [
                    'user_id' => (int) $allocation->user_id,
                    'user_name' => $allocation->user?->name,
                    'amount' => round((float) $allocation->amount, 2),
                    'percentage' => round((float) $allocation->percentage, 2),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array<int, float> $custodyByUser
     * @param array<int, float> $settlementByUser
     */
    private function ledgerEntryRow(
        AccountMemberLedgerEntry $entry,
        float $balance,
        array &$custodyByUser,
        array &$settlementByUser,
    ): array {
        $this->applyLedgerEntry($entry, $custodyByUser, $settlementByUser);

        return [
            'id' => 'ledger-'.$entry->id,
            'occurred_at' => optional($entry->occurred_at)->toDateString(),
            'source_type' => $entry->type->value,
            'transaction_id' => $entry->transaction_id,
            'label' => $entry->description,
            'description' => $this->ledgerEntryDescription($entry),
            'amount' => round((float) $entry->amount, 2),
            'balance_after' => $balance,
            'custody_after_by_user' => $this->amountRows($custodyByUser),
            'settlement_after_by_user' => $this->amountRows($settlementByUser),
            'allocations' => [],
        ];
    }

    /**
     * @param array<int, float> $custodyByUser
     * @param array<int, float> $settlementByUser
     */
    private function applyLedgerEntry(AccountMemberLedgerEntry $entry, array &$custodyByUser, array &$settlementByUser): void
    {
        $target = $this->isCustodyEntry($entry) ? 'custody' : 'settlement';

        if ($target === 'custody') {
            $custodyByUser[$entry->user_id] = round(($custodyByUser[$entry->user_id] ?? 0.0) + (float) $entry->amount, 2);

            return;
        }

        $settlementByUser[$entry->user_id] = round(($settlementByUser[$entry->user_id] ?? 0.0) + (float) $entry->amount, 2);
    }

    private function isCustodyEntry(AccountMemberLedgerEntry $entry): bool
    {
        return in_array($entry->type, [
            AccountMemberLedgerEntryType::IncomeCustody,
            AccountMemberLedgerEntryType::AccountFundExpense,
            AccountMemberLedgerEntryType::InternalTransfer,
            AccountMemberLedgerEntryType::ManualAdjustment,
            AccountMemberLedgerEntryType::CustodyCorrection,
            AccountMemberLedgerEntryType::CustodyReimbursementPayment,
        ], true);
    }

    private function isCorrectionEntry(AccountMemberLedgerEntry $entry): bool
    {
        return in_array($entry->type, [
            AccountMemberLedgerEntryType::SettlementCorrection,
            AccountMemberLedgerEntryType::CustodyCorrection,
        ], true);
    }

    private function transactionDescription(Transaction $transaction): string
    {
        if ($transaction->type === TransactionType::Income) {
            return 'Ingreso custodiado por '.($transaction->custodianUser?->name ?? $transaction->user?->name ?? 'usuario');
        }

        return 'Egreso pagado por '.($transaction->paidByUser?->name ?? $transaction->user?->name ?? 'usuario');
    }

    private function ledgerEntryDescription(AccountMemberLedgerEntry $entry): string
    {
        $userName = $entry->user?->name ?? 'Usuario';
        $relatedName = $entry->relatedUser?->name;

        return match ($entry->type) {
            AccountMemberLedgerEntryType::InternalTransfer => $relatedName
                ? "{$userName} transfirio custodia con {$relatedName}"
                : "{$userName} ajusto custodia",
            AccountMemberLedgerEntryType::SettlementTransfer => $relatedName
                ? "{$userName} liquido con {$relatedName}"
                : "{$userName} liquido un pendiente",
            AccountMemberLedgerEntryType::SettlementCorrection => $relatedName
                ? "{$userName} corrigio reembolso con {$relatedName}"
                : "{$userName} corrigio un reembolso",
            AccountMemberLedgerEntryType::CustodyCorrection => "{$userName} ajusto custodia",
            AccountMemberLedgerEntryType::CustodyReimbursementDue => $relatedName
                ? "{$userName} tiene pendiente con {$relatedName}"
                : "{$userName} tiene un pendiente de custodia",
            AccountMemberLedgerEntryType::CustodyReimbursementPayment => $relatedName
                ? "{$userName} pago custodia a {$relatedName}"
                : "{$userName} pago un pendiente de custodia",
            AccountMemberLedgerEntryType::AccountDeficitShare => "{$userName} debe aportar a la cuenta",
            AccountMemberLedgerEntryType::AccountDeficitPayment => "{$userName} aporto a la cuenta",
            default => $entry->type->value,
        };
    }

    /**
     * @param array<int, float> $amounts
     * @return array<int, array{user_id: int, user_name: string, amount: float}>
     */
    private function amountRows(array $amounts): array
    {
        $this->loadUserNames(array_keys($amounts));

        return collect($amounts)
            ->map(fn (float $amount, int $userId): array => [
                'user_id' => $userId,
                'user_name' => $this->userNames[$userId] ?? 'Usuario no disponible',
                'amount' => round($amount, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $userIds
     */
    private function loadUserNames(array $userIds): void
    {
        $missingUserIds = collect($userIds)
            ->map(fn (int|string $userId): int => (int) $userId)
            ->filter(fn (int $userId): bool => ! isset($this->userNames[$userId]))
            ->values();

        if ($missingUserIds->isEmpty()) {
            return;
        }

        User::withoutGlobalScopes()
            ->whereIn('id', $missingUserIds)
            ->pluck('name', 'id')
            ->each(function (string $name, int $userId): void {
                $this->userNames[$userId] = $name;
            });
    }
}
