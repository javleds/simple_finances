<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\ReadModels;

use FinSi\FinanceDomain\Money\Decimal;
use function array_filter;
use function array_merge;
use function array_reverse;
use function array_values;
use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use function in_array;
use function optional;

class BuildAccountMemberSummary
{
    public function __construct(private readonly BuildPendingReimbursementItems $buildPendingReimbursementItems)
    {
    }

    public function execute(Account $account): array
    {
        $users = $this->accountUsers($account);
        $entries = $account->memberLedgerEntries()->get();
        $summary = $users
            ->map(fn (User $user): array => $this->userSummary($user, $entries))
            ->values();

        return [
            'virtual_custodians'     => $summary
                ->filter(fn (array $item): bool => $users->firstWhere('id', $item['user_id'])?->isVirtualCustodian()
                    && null === $users->firstWhere('id', $item['user_id'])?->virtual_retired_at)
                ->map(fn (array $item): array => [
                    'id'                => $item['user_id'],
                    'name'              => $item['user_name'],
                    'custody_amount'    => $item['custody_amount'],
                    'settlement_amount' => $item['settlement_amount'],
                ])->values()->all(),
            'custody_by_user'        => $summary
                ->map(fn (array $item): array => [
                    'user_id'   => $item['user_id'],
                    'user_name' => $item['user_name'],
                    'amount'    => $item['custody_amount'],
                ])
                ->all(),
            'settlements_by_user'    => $summary
                ->map(fn (array $item): array => [
                    'user_id'   => $item['user_id'],
                    'user_name' => $item['user_name'],
                    'amount'    => $item['settlement_amount'],
                ])
                ->all(),
            'pending_reimbursements' => $this->pendingReimbursements($account, $summary),
        ];
    }

    private function userSummary(User $user, Collection $entries): array
    {
        $userEntries = $entries->where('user_id', $user->id);

        return [
            'user_id'           => $user->id,
            'user_name'         => $user->name,
            'custody_amount'    => $this->custodyAmount($userEntries),
            'settlement_amount' => $this->settlementAmount($userEntries),
        ];
    }

    private function custodyAmount(Collection $entries): string
    {
        return Decimal::sum(
            $entries->filter(fn (AccountMemberLedgerEntry $entry): bool => in_array($entry->type, [
                AccountMemberLedgerEntryType::IncomeCustody,
                AccountMemberLedgerEntryType::AccountFundExpense,
                AccountMemberLedgerEntryType::InternalTransfer,
                AccountMemberLedgerEntryType::ManualAdjustment,
                AccountMemberLedgerEntryType::CustodyCorrection,
                AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            ], true)),
            'amount',
        );
    }

    private function settlementAmount(Collection $entries): string
    {
        return Decimal::sum(
            $entries->filter(fn (AccountMemberLedgerEntry $entry): bool => in_array($entry->type, [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue,
                AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
                AccountMemberLedgerEntryType::AccountDeficitShare,
                AccountMemberLedgerEntryType::AccountDeficitPayment,
                AccountMemberLedgerEntryType::LegacySettlement,
            ], true)),
            'amount',
        );
    }

    private function pendingReimbursements(Account $account, Collection $summary): array
    {
        $transactionDebts = $this->transactionDebts($account);

        if ([] !== $transactionDebts) {
            return collect($transactionDebts)
                ->groupBy(fn (array $item): string => ($item['action_type'] ?? 'user_to_user') . ':' . $item['from_user_id'] . ':' . $item['to_user_id'])
                ->map(fn (Collection $items): array => [
                    ...$items->first(),
                    'amount' => Decimal::sum($items, 'amount'),
                    'items'  => $items->flatMap(fn (array $item): array => $item['items'])->values()->all(),
                ])->values()->all();
        }

        return $this->netSettlementDebts($account, $summary);
    }

    private function netSettlementDebts(Account $account, Collection $summary): array
    {
        $debtors = $summary
            ->filter(fn (array $item): bool => Decimal::compare($item['settlement_amount'], 0) < 0)
            ->map(fn (array $item): array => [...$item, 'open_amount' => Decimal::abs($item['settlement_amount'])])
            ->values();
        $creditors = $summary
            ->filter(fn (array $item): bool => Decimal::compare($item['settlement_amount'], 0) > 0)
            ->map(fn (array $item): array => [...$item, 'open_amount' => $item['settlement_amount']])
            ->values();
        $items = [];

        foreach ($debtors as &$debtor) {
            foreach ($creditors as &$creditor) {
                if (Decimal::compare($debtor['open_amount'], 0) <= 0 || Decimal::compare($creditor['open_amount'], 0) <= 0) {
                    continue;
                }

                $amount = Decimal::min($debtor['open_amount'], $creditor['open_amount']);

                if (Decimal::compare($amount, 0) <= 0) {
                    continue;
                }

                $items[] = [
                    'from_user_id'   => $debtor['user_id'],
                    'from_user_name' => $debtor['user_name'],
                    'to_user_id'     => $creditor['user_id'],
                    'to_user_name'   => $creditor['user_name'],
                    'amount'         => $amount,
                    'items'          => $this->buildPendingReimbursementItems->execute(
                        accountId: $account->id,
                        fromUserId: (int) $debtor['user_id'],
                        toUserId: (int) $creditor['user_id'],
                        totalAmount: $amount,
                    ),
                ];

                $debtor['open_amount'] = Decimal::subtract($debtor['open_amount'], $amount);
                $creditor['open_amount'] = Decimal::subtract($creditor['open_amount'], $amount);
            }
        }

        return $items;
    }

    private function transactionDebts(Account $account): array
    {
        $typedDebts = array_merge(
            $this->custodyReimbursementDebts($account),
            $this->pairedTransactionDebts(
                $account,
                [AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue, AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer],
                'custody_to_user',
                AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue,
            ),
            $this->selfCustodyReimbursementDebts($account),
            $this->accountDeficitDebts($account),
        );

        $openAmounts = $account->memberLedgerEntries()
            ->selectRaw('transaction_id, user_id, sum(amount) as open_amount')
            ->whereNotNull('transaction_id')
            ->whereNotIn('transaction_id', function ($query): void {
                $query
                    ->select('transaction_id')
                    ->from('account_member_ledger_entries')
                    ->whereNotNull('transaction_id')
                    ->whereIn('type', [
                        AccountMemberLedgerEntryType::CustodyReimbursementDue,
                        AccountMemberLedgerEntryType::AccountDeficitShare,
                    ]);
            })
            ->whereIn('type', [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
            ])
            ->groupBy('transaction_id', 'user_id')
            ->havingRaw('abs(open_amount) > 0')
            ->with('transaction')
            ->orderByDesc('transaction_id')
            ->get()
            ->groupBy('transaction_id');

        if ($openAmounts->isEmpty()) {
            return $typedDebts;
        }

        $usersById = $this->accountUsers($account)->keyBy('id');
        $reimbursementsByPair = [];

        foreach ($openAmounts as $transactionId => $transactionRows) {
            $debtors = $transactionRows
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => Decimal::compare((string) $entry->open_amount, 0) < 0)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id'     => (int) $entry->user_id,
                    'open_amount' => Decimal::abs((string) $entry->open_amount),
                    'transaction' => $entry->transaction,
                ])
                ->values();
            $creditors = $transactionRows
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => Decimal::compare((string) $entry->open_amount, 0) > 0)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id'     => (int) $entry->user_id,
                    'open_amount' => Decimal::normalize((string) $entry->open_amount),
                ])
                ->values();

            foreach ($debtors as &$debtor) {
                foreach ($creditors as &$creditor) {
                    if (Decimal::compare($debtor['open_amount'], 0) <= 0 || Decimal::compare($creditor['open_amount'], 0) <= 0) {
                        continue;
                    }

                    $amount = Decimal::min($debtor['open_amount'], $creditor['open_amount']);

                    if (Decimal::compare($amount, 0) <= 0) {
                        continue;
                    }

                    $this->appendTransactionDebt(
                        reimbursementsByPair: $reimbursementsByPair,
                        usersById: $usersById,
                        fromUserId: (int) $debtor['user_id'],
                        toUserId: (int) $creditor['user_id'],
                        transactionId: (int) $transactionId,
                        transaction: $debtor['transaction'],
                        amount: $amount,
                        actionType: 'user_to_user',
                    );

                    $debtor['open_amount'] = Decimal::subtract($debtor['open_amount'], $amount);
                    $creditor['open_amount'] = Decimal::subtract($creditor['open_amount'], $amount);
                }
            }
        }

        $this->applyDebtorCredits(
            reimbursementsByPair: $reimbursementsByPair,
            debtorCredits: $this->nonTransactionSettlementCredits($account),
        );

        $genericDebts = array_values(array_filter(
            $reimbursementsByPair,
            fn (array $item): bool => Decimal::compare($item['amount'], 0) > 0,
        ));

        return array_merge($typedDebts, $genericDebts);
    }

    private function appendTransactionDebt(
        array &$reimbursementsByPair,
        Collection $usersById,
        int $fromUserId,
        int $toUserId,
        int $transactionId,
        ?Transaction $transaction,
        string $amount,
        string $actionType,
    ): void {
        $key = "{$actionType}:{$fromUserId}:{$toUserId}";

        if (! isset($reimbursementsByPair[$key])) {
            $fromUser = $usersById->get($fromUserId);
            $toUser = $usersById->get($toUserId);

            $reimbursementsByPair[$key] = [
                'from_user_id'   => $fromUserId,
                'from_user_name' => $fromUser?->name ?? 'Usuario no disponible',
                'to_user_id'     => $toUserId,
                'to_user_name'   => $toUser?->name ?? 'Usuario no disponible',
                'amount'         => '0.00',
                'action_type'    => $actionType,
                'items'          => [],
            ];
        }

        $reimbursementsByPair[$key]['amount'] = Decimal::add($reimbursementsByPair[$key]['amount'], $amount);
        $reimbursementsByPair[$key]['items'][] = [
            'transaction_id' => (string) $transactionId,
            'concept'        => $transaction?->concept ?? 'Movimiento no disponible',
            'amount'         => $amount,
            'occurred_at'    => optional($transaction?->scheduled_at)->toDateString(),
        ];
    }

    private function custodyReimbursementDebts(Account $account): array
    {
        return $this->pairedTransactionDebts(
            account: $account,
            types: [AccountMemberLedgerEntryType::CustodyReimbursementDue, AccountMemberLedgerEntryType::SettlementTransfer],
            actionType: 'custody_to_user',
            requiredType: AccountMemberLedgerEntryType::CustodyReimbursementDue,
        );
    }

    private function selfCustodyReimbursementDebts(Account $account): array
    {
        $openAmounts = $account->memberLedgerEntries()
            ->selectRaw('transaction_id, user_id, sum(amount) as open_amount')
            ->whereNotNull('transaction_id')
            ->whereColumn('user_id', 'related_user_id')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue,
                AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
            ])
            ->groupBy('transaction_id', 'user_id')
            ->havingRaw('open_amount < 0')
            ->with('transaction')
            ->orderByDesc('transaction_id')
            ->get();

        if ($openAmounts->isEmpty()) {
            return [];
        }

        $usersById = $this->accountUsers($account)->keyBy('id');
        $reimbursementsByPair = [];

        foreach ($openAmounts as $entry) {
            $userId = (int) $entry->user_id;
            $this->appendTransactionDebt(
                reimbursementsByPair: $reimbursementsByPair,
                usersById: $usersById,
                fromUserId: $userId,
                toUserId: $userId,
                transactionId: (int) $entry->transaction_id,
                transaction: $entry->transaction,
                amount: Decimal::abs((string) $entry->open_amount),
                actionType: 'custody_to_user',
            );
        }

        return array_values($reimbursementsByPair);
    }

    private function pairedTransactionDebts(
        Account $account,
        array $types,
        string $actionType,
        ?AccountMemberLedgerEntryType $requiredType = null,
    ): array {
        $query = $account->memberLedgerEntries()
            ->selectRaw('transaction_id, user_id, sum(amount) as open_amount')
            ->whereNotNull('transaction_id')
            ->whereIn('type', $types);

        if (AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue === $requiredType) {
            $query->whereColumn('user_id', '!=', 'related_user_id');
        }

        if (null !== $requiredType) {
            $query->whereIn('transaction_id', function ($subQuery) use ($requiredType): void {
                $subQuery
                    ->select('transaction_id')
                    ->from('account_member_ledger_entries')
                    ->whereNotNull('transaction_id')
                    ->where('type', $requiredType);
            });
        }

        $openAmounts = $query
            ->groupBy('transaction_id', 'user_id')
            ->havingRaw('abs(open_amount) > 0')
            ->with('transaction')
            ->orderByDesc('transaction_id')
            ->get()
            ->groupBy('transaction_id');

        if ($openAmounts->isEmpty()) {
            return [];
        }

        $usersById = $this->accountUsers($account)->keyBy('id');
        $reimbursementsByPair = [];

        foreach ($openAmounts as $transactionId => $transactionRows) {
            $debtors = $transactionRows
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => Decimal::compare((string) $entry->open_amount, 0) < 0)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id'     => (int) $entry->user_id,
                    'open_amount' => Decimal::abs((string) $entry->open_amount),
                    'transaction' => $entry->transaction,
                ])
                ->values();
            $creditors = $transactionRows
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => Decimal::compare((string) $entry->open_amount, 0) > 0)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id'     => (int) $entry->user_id,
                    'open_amount' => Decimal::normalize((string) $entry->open_amount),
                ])
                ->values();

            foreach ($debtors as &$debtor) {
                foreach ($creditors as &$creditor) {
                    if (Decimal::compare($debtor['open_amount'], 0) <= 0 || Decimal::compare($creditor['open_amount'], 0) <= 0) {
                        continue;
                    }

                    $amount = Decimal::min($debtor['open_amount'], $creditor['open_amount']);

                    if (Decimal::compare($amount, 0) <= 0) {
                        continue;
                    }

                    $this->appendTransactionDebt(
                        reimbursementsByPair: $reimbursementsByPair,
                        usersById: $usersById,
                        fromUserId: (int) $debtor['user_id'],
                        toUserId: (int) $creditor['user_id'],
                        transactionId: (int) $transactionId,
                        transaction: $debtor['transaction'],
                        amount: $amount,
                        actionType: $actionType,
                    );

                    $debtor['open_amount'] = Decimal::subtract($debtor['open_amount'], $amount);
                    $creditor['open_amount'] = Decimal::subtract($creditor['open_amount'], $amount);
                }
            }
        }

        return array_values(array_filter(
            $reimbursementsByPair,
            fn (array $item): bool => Decimal::compare($item['amount'], 0) > 0,
        ));
    }

    private function accountDeficitDebts(Account $account): array
    {
        $entries = DB::table('account_member_ledger_entries')
            ->selectRaw('account_member_ledger_entries.*, CAST(amount AS CHAR) as amount')
            ->where('account_id', $account->id)
            ->whereNotNull('transaction_id')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::AccountDeficitShare->value,
                AccountMemberLedgerEntryType::AccountDeficitPayment->value,
            ])
            ->orderByDesc('transaction_id')
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $usersById = $this->accountUsers($account)->keyBy('id');
        $reimbursementsByPair = [];
        $openAmounts = $entries
            ->groupBy(fn (object $entry): string => $entry->transaction_id . ':' . $entry->user_id)
            ->map(function (Collection $rows): array {
                $share = $rows->firstWhere('type', AccountMemberLedgerEntryType::AccountDeficitShare->value);

                return [
                    'transaction_id'  => (int) $rows->first()->transaction_id,
                    'user_id'         => (int) $rows->first()->user_id,
                    'related_user_id' => (int) ($share?->related_user_id ?: $rows->first()->related_user_id ?: $rows->first()->user_id),
                    'open_amount'     => Decimal::sum($rows, 'amount'),
                    'transaction'     => Transaction::withoutGlobalScopes()->find((int) $rows->first()->transaction_id),
                ];
            })
            ->filter(fn (array $row): bool => Decimal::compare((string) $row['open_amount'], 0) < 0)
            ->values();

        foreach ($openAmounts as $entry) {
            $fromUserId = (int) $entry['user_id'];
            $toUserId = (int) $entry['related_user_id'];

            $this->appendTransactionDebt(
                reimbursementsByPair: $reimbursementsByPair,
                usersById: $usersById,
                fromUserId: $fromUserId,
                toUserId: $toUserId,
                transactionId: (int) $entry['transaction_id'],
                transaction: $entry['transaction'],
                amount: Decimal::abs($entry['open_amount']),
                actionType: 'user_to_account',
            );
        }

        return array_values($reimbursementsByPair);
    }

    /**
     * @return array<int, string>
     */
    private function nonTransactionSettlementCredits(Account $account): array
    {
        return $account->memberLedgerEntries()
            ->selectRaw('user_id, sum(amount) as amount')
            ->whereNull('transaction_id')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
                AccountMemberLedgerEntryType::LegacySettlement,
            ])
            ->groupBy('user_id')
            ->havingRaw('amount > 0')
            ->get()
            ->mapWithKeys(fn (AccountMemberLedgerEntry $entry): array => [
                (int) $entry->user_id => Decimal::normalize($entry->amount),
            ])
            ->all();
    }

    /**
     * @param array<string, array<string, mixed>> $reimbursementsByPair
     * @param array<int, string> $debtorCredits
     */
    private function applyDebtorCredits(array &$reimbursementsByPair, array $debtorCredits): void
    {
        foreach ($debtorCredits as $debtorUserId => $creditAmount) {
            $remainingCredit = Decimal::normalize($creditAmount);

            if (Decimal::compare($remainingCredit, 0) <= 0) {
                continue;
            }

            foreach ($reimbursementsByPair as &$reimbursement) {
                if ((int) $reimbursement['from_user_id'] !== $debtorUserId || Decimal::compare($remainingCredit, 0) <= 0) {
                    continue;
                }

                $this->reduceReimbursementFromOldestItems($reimbursement, $remainingCredit);
            }
        }
    }

    /**
     * @param array<string, mixed> $reimbursement
     */
    private function reduceReimbursementFromOldestItems(array &$reimbursement, string &$remainingCredit): void
    {
        $items = array_reverse($reimbursement['items']);
        $keptItems = [];

        foreach ($items as $item) {
            $itemAmount = Decimal::normalize($item['amount']);

            if (Decimal::compare($remainingCredit, 0) > 0) {
                $reduction = Decimal::min($itemAmount, $remainingCredit);
                $itemAmount = Decimal::subtract($itemAmount, $reduction);
                $remainingCredit = Decimal::subtract($remainingCredit, $reduction);
            }

            if (Decimal::compare($itemAmount, 0) > 0) {
                $keptItems[] = [...$item, 'amount' => $itemAmount];
            }
        }

        $reimbursement['items'] = array_reverse($keptItems);
        $reimbursement['amount'] = Decimal::sum($reimbursement['items'], 'amount');
    }

    private function accountUsers(Account $account): Collection
    {
        $users = $account->users()
            ->orderBy('users.id')
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()->where('id', $account->user_id)->get();
        }

        return $users->merge($account->virtualCustodians()->orderBy('id')->get());
    }
}
