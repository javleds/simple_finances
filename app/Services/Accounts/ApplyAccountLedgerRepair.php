<?php

namespace App\Services\Accounts;

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountLedgerRepair;
use App\Models\AccountMemberLedgerEntry;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\SharedTransactions\RegisterSharedTransactionNotificationAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyAccountLedgerRepair
{
    public function __construct(
        private readonly BuildAccountMemberSummary $buildAccountMemberSummary,
        private readonly RegisterSharedTransactionNotificationAction $registerSharedTransactionNotificationAction,
    ) {}

    public function execute(Account $account, User $actor, array $payload): AccountLedgerRepair
    {
        $repair = DB::transaction(function () use ($account, $actor, $payload): AccountLedgerRepair {
            $this->ensureRepairUsersBelongToAccount($account, $payload);

            $entries = match ($payload['repair_type']) {
                'settlement_correction' => $this->createSettlementCorrectionEntries($account, $payload),
                'custody_correction' => $this->createCustodyCorrectionEntries($account, $payload),
                default => throw new InvalidArgumentException('Tipo de corrección no soportado.'),
            };

            $freshAccount = $account->fresh();
            $resultPayload = [
                'ledger_entry_ids' => collect($entries)->pluck('id')->map(fn (int $id): string => (string) $id)->all(),
                'summary' => $this->buildAccountMemberSummary->execute($freshAccount),
                'account' => [
                    'balance' => round((float) $freshAccount->balance, 2),
                ],
            ];

            return AccountLedgerRepair::query()->create([
                'account_id' => $account->id,
                'actor_user_id' => $actor->id,
                'target_transaction_id' => isset($payload['transaction_id']) ? (int) $payload['transaction_id'] : null,
                'status' => 'applied',
                'issue_code' => $payload['issue_code'],
                'repair_type' => $payload['repair_type'],
                'confidence' => $payload['repair_type'] === 'custody_correction' ? 'user_confirmed' : 'high',
                'input_payload' => $this->normalizePayload($payload),
                'evidence_payload' => $payload['evidence'] ?? [],
                'preview_payload' => $payload['preview'] ?? $this->previewFromEntries($entries),
                'result_payload' => $resultPayload,
            ]);
        });

        $this->notifyMembers($account->fresh(), $actor, $repair);

        return $repair;
    }

    public function reverse(Account $account, User $actor, AccountLedgerRepair $repair): AccountLedgerRepair
    {
        if ((int) $repair->account_id !== (int) $account->id) {
            abort(404);
        }

        if ($repair->status !== 'applied' || $repair->reversed_by_repair_id !== null) {
            abort(422, 'Esta corrección ya fue reversada.');
        }

        $reversal = DB::transaction(function () use ($account, $actor, $repair): AccountLedgerRepair {
            $entryIds = collect($repair->result_payload['ledger_entry_ids'] ?? [])
                ->map(fn (int|string $id): int => (int) $id)
                ->filter()
                ->values();

            if ($entryIds->isEmpty()) {
                abort(422, 'La corrección no tiene asientos para reversar.');
            }

            $originalEntries = AccountMemberLedgerEntry::query()
                ->where('account_id', $account->id)
                ->whereIn('id', $entryIds)
                ->orderBy('id')
                ->get();

            if ($originalEntries->count() !== $entryIds->count()) {
                abort(422, 'No se encontraron todos los asientos originales de la corrección.');
            }

            $reversalEntries = $originalEntries
                ->map(fn (AccountMemberLedgerEntry $entry): AccountMemberLedgerEntry => $account->memberLedgerEntries()->create([
                    'user_id' => $entry->user_id,
                    'transaction_id' => $entry->transaction_id,
                    'related_user_id' => $entry->related_user_id,
                    'type' => $entry->type,
                    'amount' => round((float) $entry->amount * -1, 2),
                    'description' => 'Reversa: '.$entry->description,
                    'occurred_at' => Carbon::now(),
                ]));

            $reversalRepair = AccountLedgerRepair::query()->create([
                'account_id' => $account->id,
                'actor_user_id' => $actor->id,
                'target_transaction_id' => $repair->target_transaction_id,
                'reverses_repair_id' => $repair->id,
                'status' => 'applied',
                'issue_code' => 'repair_reversal',
                'repair_type' => $repair->repair_type,
                'confidence' => 'user_confirmed',
                'input_payload' => [
                    'description' => 'Reversa de corrección #'.$repair->id,
                    'amount' => round((float) ($repair->input_payload['amount'] ?? 0), 2),
                ],
                'evidence_payload' => [
                    'reversed_repair_id' => (string) $repair->id,
                    'original_result' => $repair->result_payload,
                ],
                'preview_payload' => $this->previewFromEntries($reversalEntries->all()),
                'result_payload' => [
                    'ledger_entry_ids' => $reversalEntries->pluck('id')->map(fn (int $id): string => (string) $id)->all(),
                    'summary' => $this->buildAccountMemberSummary->execute($account->fresh()),
                    'account' => [
                        'balance' => round((float) $account->fresh()->balance, 2),
                    ],
                ],
            ]);

            $repair->status = 'reversed';
            $repair->reversed_by_repair_id = $reversalRepair->id;
            $repair->save();

            return $reversalRepair;
        });

        $this->notifyMembers($account->fresh(), $actor, $reversal);

        return $reversal;
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createSettlementCorrectionEntries(Account $account, array $payload): array
    {
        $amount = round(abs((float) $payload['amount']), 2);
        $occurredAt = Carbon::now();

        return [
            $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['from_user_id'],
                'transaction_id' => isset($payload['transaction_id']) ? (int) $payload['transaction_id'] : null,
                'related_user_id' => (int) $payload['to_user_id'],
                'type' => AccountMemberLedgerEntryType::SettlementCorrection,
                'amount' => $amount,
                'description' => $payload['description'],
                'occurred_at' => $occurredAt,
            ]),
            $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['to_user_id'],
                'transaction_id' => isset($payload['transaction_id']) ? (int) $payload['transaction_id'] : null,
                'related_user_id' => (int) $payload['from_user_id'],
                'type' => AccountMemberLedgerEntryType::SettlementCorrection,
                'amount' => round($amount * -1, 2),
                'description' => $payload['description'],
                'occurred_at' => $occurredAt,
            ]),
        ];
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createCustodyCorrectionEntries(Account $account, array $payload): array
    {
        return [
            $account->memberLedgerEntries()->create([
                'user_id' => (int) $payload['user_id'],
                'transaction_id' => null,
                'related_user_id' => null,
                'type' => AccountMemberLedgerEntryType::CustodyCorrection,
                'amount' => round((float) $payload['amount'], 2),
                'description' => $payload['description'],
                'occurred_at' => Carbon::now(),
            ]),
        ];
    }

    private function ensureRepairUsersBelongToAccount(Account $account, array $payload): void
    {
        $account->loadMissing('users');
        $memberIds = $account->users->pluck('id')->map(fn (int $id): int => $id);
        $userIds = match ($payload['repair_type']) {
            'settlement_correction' => [(int) $payload['from_user_id'], (int) $payload['to_user_id']],
            'custody_correction' => [(int) $payload['user_id']],
            default => [],
        };

        foreach ($userIds as $userId) {
            if (! $memberIds->contains($userId)) {
                abort(422, 'La corrección solo puede usar miembros de la cuenta.');
            }
        }

        if (! isset($payload['transaction_id'])) {
            return;
        }

        $transactionBelongsToAccount = $account->transactions()
            ->withoutGlobalScopes()
            ->whereKey((int) $payload['transaction_id'])
            ->exists();

        if (! $transactionBelongsToAccount) {
            abort(422, 'La transacción no pertenece a esta cuenta.');
        }
    }

    private function normalizePayload(array $payload): array
    {
        return collect($payload)
            ->except(['evidence', 'preview'])
            ->map(function (mixed $value): mixed {
                if (is_int($value) || is_float($value)) {
                    return $value;
                }

                return is_numeric($value) ? (string) $value : $value;
            })
            ->all();
    }

    /**
     * @param array<int, AccountMemberLedgerEntry> $entries
     */
    private function previewFromEntries(array $entries): array
    {
        return [
            'summary' => 'Asientos creados por la corrección.',
            'ledger_entries' => collect($entries)
                ->map(fn (AccountMemberLedgerEntry $entry): array => [
                    'user_id' => (string) $entry->user_id,
                    'related_user_id' => $entry->related_user_id ? (string) $entry->related_user_id : null,
                    'transaction_id' => $entry->transaction_id ? (string) $entry->transaction_id : null,
                    'type' => $entry->type->value,
                    'amount' => round((float) $entry->amount, 2),
                    'description' => $entry->description,
                ])
                ->values()
                ->all(),
        ];
    }

    private function notifyMembers(Account $account, User $actor, AccountLedgerRepair $repair): void
    {
        if (config('notifications.shared_transactions.mode') !== 'grouped') {
            return;
        }

        $account->loadMissing('users');
        $description = $repair->input_payload['description'] ?? 'Corrección del libro';
        $amount = round(abs((float) ($repair->input_payload['amount'] ?? 0)), 2);

        foreach ($account->users as $user) {
            if ($user->id === $actor->id) {
                continue;
            }

            if (! $user->canReceiveNotification(NotificationType::MOVEMENTS_NOTIFICATION)) {
                continue;
            }

            if (! $user->notificableAccounts()->get()->contains($account)) {
                continue;
            }

            $this->registerSharedTransactionNotificationAction->executeSettlement(
                recipient: $user,
                modifier: $actor,
                account: $account,
                amount: $amount,
                description: 'Corrección del libro: '.$description,
                occurredAt: $repair->created_at ?? now(),
            );
        }
    }
}
