<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountLedgerRepair;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildAccountLedgerDiagnostics
{
    public function __construct(private readonly BuildAccountCustodySummary $buildAccountCustodySummary) {}

    public function execute(Account $account): array
    {
        $account->loadMissing('users');

        return [
            'diagnostics' => [
                ...$this->transactionOpenDebtDiagnostics($account),
                ...$this->positiveBalanceCustodyDiagnostics($account),
            ],
            'repairs' => $this->recentRepairs($account),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transactionOpenDebtDiagnostics(Account $account): array
    {
        $openAmounts = $account->memberLedgerEntries()
            ->selectRaw('transaction_id, user_id, round(sum(amount), 2) as open_amount')
            ->whereNotNull('transaction_id')
            ->whereIn('type', $this->settlementTypes())
            ->groupBy('transaction_id', 'user_id')
            ->havingRaw('abs(open_amount) > 0.001')
            ->with('transaction')
            ->orderByDesc('transaction_id')
            ->get()
            ->groupBy('transaction_id');

        if ($openAmounts->isEmpty()) {
            return [];
        }

        $usersById = $account->users->keyBy('id');
        $diagnostics = [];

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

                    $diagnostics[] = $this->transactionOpenDebtDiagnostic(
                        account: $account,
                        usersById: $usersById,
                        fromUserId: (int) $debtor['user_id'],
                        toUserId: (int) $creditor['user_id'],
                        amount: $amount,
                        transaction: $debtor['transaction'],
                        transactionId: (int) $transactionId,
                    );

                    $debtor['open_amount'] = round($debtor['open_amount'] - $amount, 2);
                    $creditor['open_amount'] = round($creditor['open_amount'] - $amount, 2);
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param Collection<int, User> $usersById
     */
    private function transactionOpenDebtDiagnostic(
        Account $account,
        Collection $usersById,
        int $fromUserId,
        int $toUserId,
        float $amount,
        ?Transaction $transaction,
        int $transactionId,
    ): array {
        $fromUser = $usersById->get($fromUserId);
        $toUser = $usersById->get($toUserId);
        $description = 'Corrección de reembolso: '.($transaction?->concept ?? 'movimiento');
        $suggestedPayload = [
            'repair_type' => 'settlement_correction',
            'issue_code' => 'transaction_open_debt',
            'from_user_id' => (string) $fromUserId,
            'to_user_id' => (string) $toUserId,
            'transaction_id' => (string) $transactionId,
            'amount' => $amount,
            'description' => $description,
        ];

        return [
            'id' => "transaction-open-debt:{$transactionId}:{$fromUserId}:{$toUserId}",
            'code' => 'transaction_open_debt',
            'severity' => 'warning',
            'confidence' => 'high',
            'mode' => 'automatic',
            'repair_type' => 'settlement_correction',
            'title' => 'Reembolso pendiente en movimiento',
            'description' => sprintf(
                '%s tiene por pagar %s a %s por %s.',
                $fromUser?->name ?? 'Usuario',
                number_format($amount, 2),
                $toUser?->name ?? 'usuario',
                $transaction?->concept ?? 'un movimiento'
            ),
            'target_transaction_id' => (string) $transactionId,
            'evidence' => [
                'account_id' => (string) $account->id,
                'transaction_id' => (string) $transactionId,
                'transaction_concept' => $transaction?->concept,
                'from_user_id' => (string) $fromUserId,
                'from_user_name' => $fromUser?->name ?? 'Usuario no disponible',
                'to_user_id' => (string) $toUserId,
                'to_user_name' => $toUser?->name ?? 'Usuario no disponible',
                'amount' => $amount,
            ],
            'preview' => [
                'summary' => 'Se crearán dos asientos de corrección para cerrar este reembolso sin modificar la transacción original.',
                'ledger_entries' => [
                    [
                        'user_id' => (string) $fromUserId,
                        'user_name' => $fromUser?->name ?? 'Usuario no disponible',
                        'related_user_id' => (string) $toUserId,
                        'related_user_name' => $toUser?->name ?? 'Usuario no disponible',
                        'type' => AccountMemberLedgerEntryType::SettlementCorrection->value,
                        'amount' => $amount,
                        'description' => $description,
                    ],
                    [
                        'user_id' => (string) $toUserId,
                        'user_name' => $toUser?->name ?? 'Usuario no disponible',
                        'related_user_id' => (string) $fromUserId,
                        'related_user_name' => $fromUser?->name ?? 'Usuario no disponible',
                        'type' => AccountMemberLedgerEntryType::SettlementCorrection->value,
                        'amount' => round($amount * -1, 2),
                        'description' => $description,
                    ],
                ],
            ],
            'suggested_payload' => $suggestedPayload,
            'required_fields' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function positiveBalanceCustodyDiagnostics(Account $account): array
    {
        $balance = round((float) $account->balance, 2);

        if ($balance <= 0.0) {
            return [];
        }

        $custodyTotal = round((float) $this->buildAccountCustodySummary->execute($account)->sum('amount'), 2);
        $gap = round($balance - $custodyTotal, 2);

        if (abs($gap) <= 0.01) {
            return [];
        }

        return [[
            'id' => "positive-balance-custody-gap:{$account->id}",
            'code' => 'positive_balance_custody_gap',
            'severity' => 'danger',
            'confidence' => 'needs_user_input',
            'mode' => 'needs_user_input',
            'repair_type' => 'custody_correction',
            'title' => 'Balance positivo sin custodia completa',
            'description' => 'El balance positivo debe estar custodiado por uno o más usuarios. Indica quién custodia la diferencia.',
            'target_transaction_id' => null,
            'evidence' => [
                'account_id' => (string) $account->id,
                'balance' => $balance,
                'custody_total' => $custodyTotal,
                'gap_amount' => $gap,
            ],
            'preview' => [
                'summary' => 'Se creará un asiento de custodia por la diferencia indicada. No cambia el balance de la cuenta.',
                'ledger_entries' => [],
            ],
            'suggested_payload' => [
                'repair_type' => 'custody_correction',
                'issue_code' => 'positive_balance_custody_gap',
                'amount' => $gap,
                'description' => 'Corrección de custodia',
            ],
            'required_fields' => ['user_id', 'amount', 'description'],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentRepairs(Account $account): array
    {
        return AccountLedgerRepair::query()
            ->with(['actor', 'targetTransaction'])
            ->where('account_id', $account->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (AccountLedgerRepair $repair): array {
                $inputPayload = $repair->input_payload ?? [];

                return [
                'id' => (string) $repair->id,
                'status' => $repair->status,
                'issue_code' => $repair->issue_code,
                'repair_type' => $repair->repair_type,
                'confidence' => $repair->confidence,
                'actor_user_id' => (string) $repair->actor_user_id,
                'actor_user_name' => $repair->actor?->name ?? 'Usuario no disponible',
                'target_transaction_id' => $repair->target_transaction_id ? (string) $repair->target_transaction_id : null,
                'target_transaction_concept' => $repair->targetTransaction?->concept,
                'description' => $inputPayload['description'] ?? $repair->issue_code,
                'amount' => round((float) ($inputPayload['amount'] ?? 0), 2),
                'created_at' => optional($repair->created_at)->toIso8601String(),
                'can_reverse' => $repair->status === 'applied' && $repair->reversed_by_repair_id === null,
                'preview' => $repair->preview_payload ?? [],
                'result' => $repair->result_payload ?? [],
                ];
            })
            ->all();
    }

    /**
     * @return array<int, AccountMemberLedgerEntryType>
     */
    private function settlementTypes(): array
    {
        return [
            AccountMemberLedgerEntryType::ExpensePaid,
            AccountMemberLedgerEntryType::ExpenseShare,
            AccountMemberLedgerEntryType::SettlementTransfer,
            AccountMemberLedgerEntryType::SettlementCorrection,
        ];
    }
}
