<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildAccountMemberSummary
{
    public function __construct(private readonly BuildPendingReimbursementItems $buildPendingReimbursementItems) {}

    public function execute(Account $account): array
    {
        $users = $this->accountUsers($account);
        $entries = $account->memberLedgerEntries()->get();
        $summary = $users
            ->map(fn (User $user): array => $this->userSummary($user, $entries))
            ->values();

        return [
            'custody_by_user' => $summary
                ->map(fn (array $item): array => [
                    'user_id' => $item['user_id'],
                    'user_name' => $item['user_name'],
                    'amount' => $item['custody_amount'],
                ])
                ->all(),
            'settlements_by_user' => $summary
                ->map(fn (array $item): array => [
                    'user_id' => $item['user_id'],
                    'user_name' => $item['user_name'],
                    'amount' => $item['settlement_amount'],
                ])
                ->all(),
            'pending_reimbursements' => $this->pendingReimbursements($account, $summary),
        ];
    }

    private function userSummary(User $user, Collection $entries): array
    {
        $userEntries = $entries->where('user_id', $user->id);

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'custody_amount' => round($this->custodyAmount($userEntries), 2),
            'settlement_amount' => round($this->settlementAmount($userEntries), 2),
        ];
    }

    private function custodyAmount(Collection $entries): float
    {
        return (float) $entries
            ->filter(fn (AccountMemberLedgerEntry $entry): bool => in_array($entry->type, [
                AccountMemberLedgerEntryType::IncomeCustody,
                AccountMemberLedgerEntryType::AccountFundExpense,
                AccountMemberLedgerEntryType::InternalTransfer,
                AccountMemberLedgerEntryType::ManualAdjustment,
                AccountMemberLedgerEntryType::CustodyCorrection,
                AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            ], true))
            ->sum('amount');
    }

    private function settlementAmount(Collection $entries): float
    {
        return (float) $entries
            ->filter(fn (AccountMemberLedgerEntry $entry): bool => in_array($entry->type, [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::AccountDeficitShare,
                AccountMemberLedgerEntryType::AccountDeficitPayment,
                AccountMemberLedgerEntryType::LegacySettlement,
            ], true))
            ->sum('amount');
    }

    private function pendingReimbursements(Account $account, Collection $summary): array
    {
        $transactionDebts = $this->transactionDebts($account);

        if ($transactionDebts !== []) {
            return $transactionDebts;
        }

        return $this->netSettlementDebts($account, $summary);
    }

    private function netSettlementDebts(Account $account, Collection $summary): array
    {
        $debtors = $summary
            ->filter(fn (array $item): bool => $item['settlement_amount'] < 0)
            ->map(fn (array $item): array => [...$item, 'open_amount' => abs($item['settlement_amount'])])
            ->values();
        $creditors = $summary
            ->filter(fn (array $item): bool => $item['settlement_amount'] > 0)
            ->map(fn (array $item): array => [...$item, 'open_amount' => $item['settlement_amount']])
            ->values();
        $items = [];

        foreach ($debtors as &$debtor) {
            foreach ($creditors as &$creditor) {
                if ($debtor['open_amount'] <= 0 || $creditor['open_amount'] <= 0) {
                    continue;
                }

                $amount = round(min($debtor['open_amount'], $creditor['open_amount']), 2);

                if ($amount <= 0) {
                    continue;
                }

                $items[] = [
                    'from_user_id' => $debtor['user_id'],
                    'from_user_name' => $debtor['user_name'],
                    'to_user_id' => $creditor['user_id'],
                    'to_user_name' => $creditor['user_name'],
                    'amount' => $amount,
                    'items' => $this->buildPendingReimbursementItems->execute(
                        accountId: $account->id,
                        fromUserId: (int) $debtor['user_id'],
                        toUserId: (int) $creditor['user_id'],
                        totalAmount: $amount,
                    ),
                ];

                $debtor['open_amount'] = round($debtor['open_amount'] - $amount, 2);
                $creditor['open_amount'] = round($creditor['open_amount'] - $amount, 2);
            }
        }

        return $items;
    }

    private function transactionDebts(Account $account): array
    {
        $typedDebts = array_merge(
            $this->custodyReimbursementDebts($account),
            $this->selfCustodyReimbursementDebts($account),
            $this->accountDeficitDebts($account),
        );

        $openAmounts = $account->memberLedgerEntries()
            ->selectRaw('transaction_id, user_id, round(sum(amount), 2) as open_amount')
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
            ->havingRaw('abs(open_amount) > 0.001')
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
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => (float) $entry->open_amount < -0.001)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id' => (int) $entry->user_id,
                    'open_amount' => abs(round((float) $entry->open_amount, 2)),
                    'transaction' => $entry->transaction,
                ])
                ->values();
            $creditors = $transactionRows
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => (float) $entry->open_amount > 0.001)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id' => (int) $entry->user_id,
                    'open_amount' => round((float) $entry->open_amount, 2),
                ])
                ->values();

            foreach ($debtors as &$debtor) {
                foreach ($creditors as &$creditor) {
                    if ($debtor['open_amount'] <= 0.0 || $creditor['open_amount'] <= 0.0) {
                        continue;
                    }

                    $amount = round(min($debtor['open_amount'], $creditor['open_amount']), 2);

                    if ($amount <= 0.0) {
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

                    $debtor['open_amount'] = round($debtor['open_amount'] - $amount, 2);
                    $creditor['open_amount'] = round($creditor['open_amount'] - $amount, 2);
                }
            }
        }

        $this->applyDebtorCredits(
            reimbursementsByPair: $reimbursementsByPair,
            debtorCredits: $this->nonTransactionSettlementCredits($account),
        );

        $genericDebts = array_values(array_filter(
            $reimbursementsByPair,
            fn (array $item): bool => (float) $item['amount'] > 0.0,
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
        float $amount,
        string $actionType,
    ): void {
        $key = "{$actionType}:{$fromUserId}:{$toUserId}";

        if (! isset($reimbursementsByPair[$key])) {
            $fromUser = $usersById->get($fromUserId);
            $toUser = $usersById->get($toUserId);

            $reimbursementsByPair[$key] = [
                'from_user_id' => $fromUserId,
                'from_user_name' => $fromUser?->name ?? 'Usuario no disponible',
                'to_user_id' => $toUserId,
                'to_user_name' => $toUser?->name ?? 'Usuario no disponible',
                'amount' => 0.0,
                'action_type' => $actionType,
                'items' => [],
            ];
        }

        $reimbursementsByPair[$key]['amount'] = round(
            (float) $reimbursementsByPair[$key]['amount'] + $amount,
            2,
        );
        $reimbursementsByPair[$key]['items'][] = [
            'transaction_id' => (string) $transactionId,
            'concept' => $transaction?->concept ?? 'Movimiento no disponible',
            'amount' => $amount,
            'occurred_at' => optional($transaction?->scheduled_at)->toDateString(),
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
            ->selectRaw('transaction_id, user_id, round(sum(amount), 2) as open_amount')
            ->whereNotNull('transaction_id')
            ->whereColumn('user_id', 'related_user_id')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::SettlementTransfer,
            ])
            ->groupBy('transaction_id', 'user_id')
            ->havingRaw('open_amount < -0.001')
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
                amount: abs(round((float) $entry->open_amount, 2)),
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
            ->selectRaw('transaction_id, user_id, round(sum(amount), 2) as open_amount')
            ->whereNotNull('transaction_id')
            ->whereIn('type', $types);

        if ($requiredType !== null) {
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
            ->havingRaw('abs(open_amount) > 0.001')
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
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => (float) $entry->open_amount < -0.001)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id' => (int) $entry->user_id,
                    'open_amount' => abs(round((float) $entry->open_amount, 2)),
                    'transaction' => $entry->transaction,
                ])
                ->values();
            $creditors = $transactionRows
                ->filter(fn (AccountMemberLedgerEntry $entry): bool => (float) $entry->open_amount > 0.001)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id' => (int) $entry->user_id,
                    'open_amount' => round((float) $entry->open_amount, 2),
                ])
                ->values();

            foreach ($debtors as &$debtor) {
                foreach ($creditors as &$creditor) {
                    if ($debtor['open_amount'] <= 0.0 || $creditor['open_amount'] <= 0.0) {
                        continue;
                    }

                    $amount = round(min($debtor['open_amount'], $creditor['open_amount']), 2);

                    if ($amount <= 0.0) {
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

                    $debtor['open_amount'] = round($debtor['open_amount'] - $amount, 2);
                    $creditor['open_amount'] = round($creditor['open_amount'] - $amount, 2);
                }
            }
        }

        return array_values(array_filter(
            $reimbursementsByPair,
            fn (array $item): bool => (float) $item['amount'] > 0.0,
        ));
    }

    private function accountDeficitDebts(Account $account): array
    {
        $entries = DB::table('account_member_ledger_entries')
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
            ->groupBy(fn (object $entry): string => $entry->transaction_id.':'.$entry->user_id)
            ->map(function (Collection $rows): array {
                $share = $rows->firstWhere('type', AccountMemberLedgerEntryType::AccountDeficitShare->value);

                return [
                    'transaction_id' => (int) $rows->first()->transaction_id,
                    'user_id' => (int) $rows->first()->user_id,
                    'related_user_id' => (int) ($share?->related_user_id ?: $rows->first()->related_user_id ?: $rows->first()->user_id),
                    'open_amount' => round((float) $rows->sum('amount'), 2),
                    'transaction' => Transaction::withoutGlobalScopes()->find((int) $rows->first()->transaction_id),
                ];
            })
            ->filter(fn (array $row): bool => (float) $row['open_amount'] < -0.001)
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
                amount: abs(round((float) $entry['open_amount'], 2)),
                actionType: 'user_to_account',
            );
        }

        return array_values($reimbursementsByPair);
    }

    /**
     * @return array<int, float>
     */
    private function nonTransactionSettlementCredits(Account $account): array
    {
        return $account->memberLedgerEntries()
            ->selectRaw('user_id, round(sum(amount), 2) as amount')
            ->whereNull('transaction_id')
            ->whereIn('type', [
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
                AccountMemberLedgerEntryType::LegacySettlement,
            ])
            ->groupBy('user_id')
            ->havingRaw('amount > 0.001')
            ->get()
            ->mapWithKeys(fn (AccountMemberLedgerEntry $entry): array => [
                (int) $entry->user_id => round((float) $entry->amount, 2),
            ])
            ->all();
    }

    /**
     * @param array<string, array<string, mixed>> $reimbursementsByPair
     * @param array<int, float> $debtorCredits
     */
    private function applyDebtorCredits(array &$reimbursementsByPair, array $debtorCredits): void
    {
        foreach ($debtorCredits as $debtorUserId => $creditAmount) {
            $remainingCredit = round($creditAmount, 2);

            if ($remainingCredit <= 0.0) {
                continue;
            }

            foreach ($reimbursementsByPair as &$reimbursement) {
                if ((int) $reimbursement['from_user_id'] !== $debtorUserId || $remainingCredit <= 0.0) {
                    continue;
                }

                $this->reduceReimbursementFromOldestItems($reimbursement, $remainingCredit);
            }
        }
    }

    /**
     * @param array<string, mixed> $reimbursement
     */
    private function reduceReimbursementFromOldestItems(array &$reimbursement, float &$remainingCredit): void
    {
        $items = array_reverse($reimbursement['items']);
        $keptItems = [];

        foreach ($items as $item) {
            $itemAmount = round((float) $item['amount'], 2);

            if ($remainingCredit > 0.0) {
                $reduction = round(min($itemAmount, $remainingCredit), 2);
                $itemAmount = round($itemAmount - $reduction, 2);
                $remainingCredit = round($remainingCredit - $reduction, 2);
            }

            if ($itemAmount > 0.0) {
                $keptItems[] = [...$item, 'amount' => $itemAmount];
            }
        }

        $reimbursement['items'] = array_reverse($keptItems);
        $reimbursement['amount'] = round((float) collect($reimbursement['items'])->sum('amount'), 2);
    }

    private function accountUsers(Account $account): Collection
    {
        $users = $account->users()
            ->orderBy('users.id')
            ->get();

        if ($users->isNotEmpty()) {
            return $users;
        }

        return User::query()
            ->where('id', $account->user_id)
            ->get();
    }
}
