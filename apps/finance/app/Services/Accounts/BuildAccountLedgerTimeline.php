<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use function array_keys;
use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Collection;

use function in_array;
use function optional;

class BuildAccountLedgerTimeline
{
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
                'sort_id'   => $transaction->id,
                'kind'      => 'transaction',
                'payload'   => $transaction,
            ])
            ->merge($entries->map(fn (AccountMemberLedgerEntry $entry): array => [
                'sort_date' => $entry->occurred_at,
                'sort_id'   => $entry->id,
                'kind'      => 'ledger_entry',
                'payload'   => $entry,
            ]))
            ->sortBy([
                ['sort_date', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values();

        $balance = '0.00';
        $custodyByUser = [];
        $settlementByUser = [];

        return $events
            ->map(function (array $event) use (&$balance, &$custodyByUser, &$settlementByUser): array {
                if ('transaction' === $event['kind']) {
                    return $this->transactionRow($event['payload'], $balance, $custodyByUser, $settlementByUser);
                }

                return $this->ledgerEntryRow($event['payload'], $balance, $custodyByUser, $settlementByUser);
            })
            ->reverse()
            ->values();
    }

    /** @var array<int, string> */
    private array $userNames = [];

    /**
     * @param array<int, string> $custodyByUser
     * @param array<int, string> $settlementByUser
     */
    private function transactionRow(
        Transaction $transaction,
        string &$balance,
        array &$custodyByUser,
        array &$settlementByUser,
    ): array {
        $balance = TransactionType::Income === $transaction->type
            ? Decimal::add($balance, Decimal::normalize($transaction->amount))
            : Decimal::subtract($balance, Decimal::normalize($transaction->amount));

        foreach ($transaction->subTransactions as $subTransaction) {
            if (TransactionType::Income === $subTransaction->type) {
                $balance = Decimal::add($balance, Decimal::normalize($subTransaction->amount));
            }
        }

        foreach ($transaction->ledgerEntries->reject(fn (AccountMemberLedgerEntry $entry): bool => $this->isCorrectionEntry($entry)) as $entry) {
            $this->applyLedgerEntry($entry, $custodyByUser, $settlementByUser);
        }

        return [
            'id'                       => 'transaction-' . $transaction->id,
            'occurred_at'              => optional($transaction->scheduled_at)->toDateString(),
            'source_type'              => 'transaction',
            'transaction_id'           => $transaction->id,
            'label'                    => $transaction->concept,
            'description'              => $this->transactionDescription($transaction),
            'amount'                   => Decimal::normalize($transaction->amount),
            'balance_after'            => $balance,
            'custody_after_by_user'    => $this->amountRows($custodyByUser),
            'settlement_after_by_user' => $this->amountRows($settlementByUser),
            'allocations'              => $transaction->allocations
                ->map(fn ($allocation): array => [
                    'user_id'    => (int) $allocation->user_id,
                    'user_name'  => $allocation->user?->name,
                    'amount'     => Decimal::normalize($allocation->amount),
                    'percentage' => Decimal::normalize($allocation->percentage),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array<int, string> $custodyByUser
     * @param array<int, string> $settlementByUser
     */
    private function ledgerEntryRow(
        AccountMemberLedgerEntry $entry,
        string $balance,
        array &$custodyByUser,
        array &$settlementByUser,
    ): array {
        $this->applyLedgerEntry($entry, $custodyByUser, $settlementByUser);

        return [
            'id'                       => 'ledger-' . $entry->id,
            'occurred_at'              => optional($entry->occurred_at)->toDateString(),
            'source_type'              => $entry->type->value,
            'transaction_id'           => $entry->transaction_id,
            'label'                    => $entry->description,
            'description'              => $this->ledgerEntryDescription($entry),
            'amount'                   => Decimal::normalize($entry->amount),
            'balance_after'            => $balance,
            'custody_after_by_user'    => $this->amountRows($custodyByUser),
            'settlement_after_by_user' => $this->amountRows($settlementByUser),
            'allocations'              => [],
        ];
    }

    /**
     * @param array<int, string> $custodyByUser
     * @param array<int, string> $settlementByUser
     */
    private function applyLedgerEntry(AccountMemberLedgerEntry $entry, array &$custodyByUser, array &$settlementByUser): void
    {
        $target = $this->isCustodyEntry($entry) ? 'custody' : 'settlement';

        if ('custody' === $target) {
            $custodyByUser[$entry->user_id] = Decimal::add($custodyByUser[$entry->user_id] ?? '0.00', Decimal::normalize($entry->amount));

            return;
        }

        $settlementByUser[$entry->user_id] = Decimal::add($settlementByUser[$entry->user_id] ?? '0.00', Decimal::normalize($entry->amount));
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
        if (TransactionType::Income === $transaction->type) {
            return 'Ingreso custodiado por ' . ($transaction->custodianUser?->name ?? $transaction->user?->name ?? 'usuario');
        }

        return 'Egreso pagado por ' . ($transaction->paidByUser?->name ?? $transaction->user?->name ?? 'usuario');
    }

    private function ledgerEntryDescription(AccountMemberLedgerEntry $entry): string
    {
        $userName = $entry->user?->name ?? 'Usuario';
        $relatedName = $entry->relatedUser?->name;

        return match ($entry->type) {
            AccountMemberLedgerEntryType::InternalTransfer            => $relatedName
                ? "{$userName} transfirio custodia con {$relatedName}"
                : "{$userName} ajusto custodia",
            AccountMemberLedgerEntryType::SettlementTransfer, AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer => $relatedName
                ? "{$userName} liquido con {$relatedName}"
                : "{$userName} liquido un pendiente",
            AccountMemberLedgerEntryType::SettlementCorrection        => $relatedName
                ? "{$userName} corrigio reembolso con {$relatedName}"
                : "{$userName} corrigio un reembolso",
            AccountMemberLedgerEntryType::CustodyCorrection           => "{$userName} ajusto custodia",
            AccountMemberLedgerEntryType::CustodyReimbursementDue, AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue => $relatedName
                ? "{$userName} tiene pendiente con {$relatedName}"
                : "{$userName} tiene un pendiente de custodia",
            AccountMemberLedgerEntryType::CustodyReimbursementPayment => $relatedName
                ? "{$userName} pago custodia a {$relatedName}"
                : "{$userName} pago un pendiente de custodia",
            AccountMemberLedgerEntryType::AccountDeficitShare         => "{$userName} debe aportar a la cuenta",
            AccountMemberLedgerEntryType::AccountDeficitPayment       => "{$userName} aporto a la cuenta",
            default                                                   => $entry->type->value,
        };
    }

    /**
     * @param array<int, string> $amounts
     *
     * @return array<int, array{user_id: int, user_name: string, amount: string}>
     */
    private function amountRows(array $amounts): array
    {
        $this->loadUserNames(array_keys($amounts));

        return collect($amounts)
            ->map(fn (string $amount, int $userId): array => [
                'user_id'   => $userId,
                'user_name' => $this->userNames[$userId] ?? 'Usuario no disponible',
                'amount'    => Decimal::round($amount, 2),
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
            ->map(fn (int | string $userId): int => (int) $userId)
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
