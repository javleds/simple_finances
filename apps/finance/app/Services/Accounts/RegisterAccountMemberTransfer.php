<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use function abs;
use function app;

use App\Services\PersonalMoney\CapturePersonalMoneyReimbursements;
use App\Services\Transaction\SyncAccountMemberLedger;

use function array_filter;
use function array_values;
use function auth;
use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function min;
use function round;

class RegisterAccountMemberTransfer
{
    public function __construct(
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
        private readonly SyncAccountMemberLedger $syncAccountMemberLedger,
        private readonly CapturePersonalMoneyReimbursements $capturePersonalMoneyReimbursements,
    ) {
    }

    public function execute(Account $account, array $payload): array
    {
        $amount = round((float) $payload['amount'], 2);
        $occurredAt = isset($payload['occurred_at'])
            ? Carbon::parse($payload['occurred_at'])
            : Carbon::now();
        $description = $payload['description'] ?? 'Transferencia interna';

        return DB::transaction(function () use ($account, $payload, $amount, $occurredAt, $description): array {
            Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $actionType = $payload['action_type'] ?? 'user_to_user';

            if ($account->virtualCustodians()->exists()) {
                $this->validateVirtualAccountTransfer($account, $payload, $amount, $actionType);
                $virtualEntries = $this->virtualCustodySettlementEntries($account, $payload, $amount, $occurredAt, $description);
                if (null !== $virtualEntries) {
                    $this->capturePersonalMoneyReimbursements->execute($account, $payload, $virtualEntries);

                    return $virtualEntries;
                }
            }

            if ('user_to_account' === $actionType) {
                return $this->createAccountDeficitPayment(
                    account: $account,
                    fromUserId: (int) $payload['from_user_id'],
                    toUserId: (int) $payload['to_user_id'],
                    amount: $amount,
                    description: $description,
                    occurredAt: $occurredAt,
                );
            }

            $entries = 'custody_to_user' === $actionType && (int) $payload['from_user_id'] === (int) $payload['to_user_id']
                ? $this->createSelfSettlementEntries(
                    account: $account,
                    userId: (int) $payload['from_user_id'],
                    amount: $amount,
                    description: $description,
                    occurredAt: $occurredAt,
                )
                : $this->createSettlementEntries(
                    account: $account,
                    fromUserId: (int) $payload['from_user_id'],
                    toUserId: (int) $payload['to_user_id'],
                    amount: $amount,
                    description: $description,
                    occurredAt: $occurredAt,
                    types: 'custody_to_user' === $actionType
                        ? [AccountMemberLedgerEntryType::CustodyReimbursementDue, AccountMemberLedgerEntryType::SettlementTransfer]
                        : null,
                );

            if ('custody_to_user' === $actionType) {
                $entries[] = $account->memberLedgerEntries()->create([
                    'user_id'         => (int) $payload['from_user_id'],
                    'related_user_id' => (int) $payload['to_user_id'],
                    'type'            => AccountMemberLedgerEntryType::CustodyReimbursementPayment,
                    'amount'          => $amount * -1,
                    'description'     => $description,
                    'occurred_at'     => $occurredAt,
                ]);
            }

            if ('user_to_user' === $actionType) {
                $this->createNegativeBalanceSettlementIncomes(
                    account: $account,
                    entries: $entries,
                    description: $description,
                    occurredAt: $occurredAt,
                );
            }

            $this->capturePersonalMoneyReimbursements->execute($account, $payload, $entries);

            return $entries;
        });
    }
    private const ROUNDING_SETTLEMENT_TOLERANCE = 0.10;

    private function validateVirtualAccountTransfer(Account $account, array $payload, float $amount, string $actionType): void
    {
        $pending = app(BuildAccountMemberSummary::class)->execute($account)['pending_reimbursements'];
        $available = collect($pending)->filter(fn (array $item): bool => (int) $item['from_user_id'] === (int) $payload['from_user_id']
            && (int) $item['to_user_id'] === (int) $payload['to_user_id']
            && ($item['action_type'] ?? 'user_to_user') === $actionType)->sum('amount');

        if ((int) round($amount * 100) > (int) round($available * 100)) {
            throw ValidationException::withMessages(['amount' => 'El importe supera el reembolso pendiente.']);
        }

        if ('custody_to_user' !== $actionType) {
            return;
        }

        $custody = app(BuildAccountCustodySummary::class)->execute($account)->firstWhere('user_id', (int) $payload['from_user_id']);
        if ((int) round($amount * 100) > (int) round(($custody['amount'] ?? 0) * 100)) {
            throw ValidationException::withMessages(['amount' => 'La custodia disponible es insuficiente.']);
        }
    }

    private function virtualCustodySettlementEntries(Account $account, array $payload, float $amount, Carbon $occurredAt, string $description): ?array
    {
        if (($payload['action_type'] ?? '') !== 'custody_to_user') {
            return null;
        }

        $fromId = (int) $payload['from_user_id'];
        $toId = (int) $payload['to_user_id'];
        $items = $account->memberLedgerEntries()
            ->selectRaw('transaction_id, round(-sum(amount), 2) as open_amount')
            ->where('user_id', $fromId)->where('related_user_id', $toId)
            ->whereIn('type', [AccountMemberLedgerEntryType::VirtualCustodyReimbursementDue, AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer])
            ->whereNotNull('transaction_id')->groupBy('transaction_id')
            ->havingRaw('sum(amount) < -0.001')->orderBy('transaction_id')->get();

        if ($items->isEmpty()) {
            return null;
        }

        $entries = [];
        $remaining = $amount;
        foreach ($items as $item) {
            $paid = round(min($remaining, (float) $item->open_amount), 2);
            if ($paid <= 0) {
                break;
            }
            $entries[] = $account->memberLedgerEntries()->create([
                'user_id' => $fromId, 'related_user_id' => $toId, 'transaction_id' => $item->transaction_id,
                'type'    => AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
                'amount'  => $paid, 'description' => $description, 'occurred_at' => $occurredAt,
            ]);
            if ($fromId !== $toId) {
                $entries[] = $account->memberLedgerEntries()->create([
                    'user_id' => $toId, 'related_user_id' => $fromId, 'transaction_id' => $item->transaction_id,
                    'type'    => AccountMemberLedgerEntryType::VirtualCustodySettlementTransfer,
                    'amount'  => -$paid, 'description' => $description, 'occurred_at' => $occurredAt,
                ]);
            }
            $remaining = round($remaining - $paid, 2);
        }

        if ($remaining > 0.0) {
            $legacyEntries = $fromId === $toId
                ? $this->createSelfSettlementEntries($account, $fromId, $remaining, $description, $occurredAt)
                : $this->createSettlementEntries(
                    $account, $fromId, $toId, $remaining, $description, $occurredAt,
                    [AccountMemberLedgerEntryType::CustodyReimbursementDue, AccountMemberLedgerEntryType::SettlementTransfer],
                );
            $entries = [...$entries, ...$legacyEntries];
        }
        $entries[] = $account->memberLedgerEntries()->create([
            'user_id' => $fromId, 'related_user_id' => $toId,
            'type'    => AccountMemberLedgerEntryType::CustodyReimbursementPayment,
            'amount'  => -$amount, 'description' => $description, 'occurred_at' => $occurredAt,
        ]);

        return $entries;
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createAccountDeficitPayment(
        Account $account,
        int $fromUserId,
        int $toUserId,
        float $amount,
        string $description,
        Carbon $occurredAt,
    ): array {
        $income = new Transaction();
        $income->type = TransactionType::Income;
        $income->status = TransactionStatus::Completed;
        $income->concept = $description;
        $income->amount = $amount;
        $income->percentage = 100.0;
        $income->account_id = $account->id;
        $income->custodian_user_id = $toUserId;
        $income->scheduled_at = $occurredAt;
        $income->user_id = auth()->id() ?? $fromUserId;
        $income->save();

        $this->syncAccountMemberLedger->execute($income);
        $this->recalculateAccountBalance->execute($account);

        $entries = [$income->ledgerEntries()->first()];
        $items = $this->openTransactionDebts($account, $fromUserId, [
            AccountMemberLedgerEntryType::AccountDeficitShare,
            AccountMemberLedgerEntryType::AccountDeficitPayment,
        ]);
        $runningTotal = 0.0;

        foreach ($items as $item) {
            if ($runningTotal >= $amount) {
                break;
            }

            $itemAmount = round(min((float) $item->open_amount, $amount - $runningTotal), 2);

            if ($itemAmount <= 0.0) {
                continue;
            }

            $entries[] = $account->memberLedgerEntries()->create([
                'user_id'         => $fromUserId,
                'transaction_id'  => (int) $item->transaction_id,
                'related_user_id' => $toUserId,
                'type'            => AccountMemberLedgerEntryType::AccountDeficitPayment,
                'amount'          => $itemAmount,
                'description'     => $description,
                'occurred_at'     => $occurredAt,
            ]);

            $runningTotal = round($runningTotal + $itemAmount, 2);
        }

        return array_values(array_filter($entries));
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createSelfSettlementEntries(
        Account $account,
        int $userId,
        float $amount,
        string $description,
        Carbon $occurredAt,
    ): array {
        $items = $this->openTransactionDebts($account, $userId, [
            AccountMemberLedgerEntryType::CustodyReimbursementDue,
            AccountMemberLedgerEntryType::SettlementTransfer,
        ]);
        $entries = [];
        $runningTotal = 0.0;

        foreach ($items as $item) {
            if ($runningTotal >= $amount) {
                break;
            }

            $itemAmount = round(min((float) $item->open_amount, $amount - $runningTotal), 2);

            if ($itemAmount <= 0.0) {
                continue;
            }

            $entries[] = $this->createSettlementEntry(
                $account,
                $userId,
                $userId,
                $itemAmount,
                $description,
                $occurredAt,
                (int) $item->transaction_id,
            );
            $runningTotal = round($runningTotal + $itemAmount, 2);
        }

        return $entries;
    }

    /**
     * @return array<int, AccountMemberLedgerEntry>
     */
    private function createSettlementEntries(
        Account $account,
        int $fromUserId,
        int $toUserId,
        float $amount,
        string $description,
        Carbon $occurredAt,
        ?array $types = null,
    ): array {
        $items = $this->openTransactionDebts($account, $fromUserId, $types, $toUserId);

        if ($items->isEmpty()) {
            return [
                $this->createSettlementEntry($account, $fromUserId, $toUserId, $amount, $description, $occurredAt),
                $this->createSettlementEntry($account, $toUserId, $fromUserId, $amount * -1, $description, $occurredAt),
            ];
        }

        $entries = [];
        $runningTotal = 0.0;
        $transactionDebtTotal = round((float) $items->sum('open_amount'), 2);
        $amountToAllocate = $this->shouldCloseAllTransactionDebts($amount, $transactionDebtTotal)
            ? $transactionDebtTotal
            : $amount;

        foreach ($items as $item) {
            $remaining = round($amountToAllocate - $runningTotal, 2);

            if ($remaining <= 0.0) {
                break;
            }

            $itemAmount = round(min((float) $item->open_amount, $remaining), 2);

            if ($itemAmount <= 0.0) {
                continue;
            }

            $entries[] = $this->createSettlementEntry(
                $account,
                $fromUserId,
                $toUserId,
                $itemAmount,
                $description,
                $occurredAt,
                (int) $item->transaction_id,
            );
            $entries[] = $this->createSettlementEntry(
                $account,
                $toUserId,
                $fromUserId,
                $itemAmount * -1,
                $description,
                $occurredAt,
                (int) $item->transaction_id,
            );

            $runningTotal = round($runningTotal + $itemAmount, 2);
        }

        $unallocatedAmount = round($amount - $runningTotal, 2);

        if (abs($unallocatedAmount) > 0.001) {
            $entries[] = $this->createSettlementEntry($account, $fromUserId, $toUserId, $unallocatedAmount, $description, $occurredAt);
            $entries[] = $this->createSettlementEntry($account, $toUserId, $fromUserId, $unallocatedAmount * -1, $description, $occurredAt);
        }

        return $entries;
    }

    /**
     * @return Collection<int, AccountMemberLedgerEntry>
     */
    private function openTransactionDebts(Account $account, int $fromUserId, ?array $types = null, ?int $toUserId = null): Collection
    {
        return $account->memberLedgerEntries()
            ->selectRaw('transaction_id, round(abs(sum(amount)), 2) as open_amount')
            ->where('user_id', $fromUserId)
            ->when(null !== $toUserId && $account->virtualCustodians()->exists(), fn ($query) => $query->where('related_user_id', $toUserId))
            ->whereNotNull('transaction_id')
            ->whereIn('type', $types ?? [
                AccountMemberLedgerEntryType::ExpensePaid,
                AccountMemberLedgerEntryType::ExpenseShare,
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::SettlementTransfer,
                AccountMemberLedgerEntryType::SettlementCorrection,
            ])
            ->groupBy('transaction_id')
            ->havingRaw('sum(amount) < -0.001')
            ->orderByDesc('transaction_id')
            ->get();
    }

    private function shouldCloseAllTransactionDebts(float $amount, float $transactionDebtTotal): bool
    {
        if ($transactionDebtTotal <= 0.0) {
            return false;
        }

        return abs(round($transactionDebtTotal - $amount, 2)) <= self::ROUNDING_SETTLEMENT_TOLERANCE;
    }

    private function createSettlementEntry(
        Account $account,
        int $userId,
        int $relatedUserId,
        float $amount,
        string $description,
        Carbon $occurredAt,
        ?int $transactionId = null,
    ): AccountMemberLedgerEntry {
        return $account->memberLedgerEntries()->create([
            'user_id'         => $userId,
            'transaction_id'  => $transactionId,
            'related_user_id' => $relatedUserId,
            'type'            => AccountMemberLedgerEntryType::SettlementTransfer,
            'amount'          => $amount,
            'description'     => $description,
            'occurred_at'     => $occurredAt,
        ]);
    }

    /**
     * @param array<int, AccountMemberLedgerEntry> $entries
     */
    private function createNegativeBalanceSettlementIncomes(
        Account $account,
        array $entries,
        string $description,
        Carbon $occurredAt,
    ): void {
        $remainingDeficit = round(abs(min((float) $account->fresh()->balance, 0.0)), 2);

        if ($remainingDeficit <= 0.0) {
            return;
        }

        $createdTotal = 0.0;

        foreach ($entries as $entry) {
            if ($remainingDeficit <= 0.0) {
                break;
            }

            if (AccountMemberLedgerEntryType::SettlementTransfer !== $entry->type
                || (float) $entry->amount <= 0.0
                || null === $entry->transaction_id
            ) {
                continue;
            }

            $incomeAmount = round(min((float) $entry->amount, $remainingDeficit), 2);

            if ($incomeAmount <= 0.0) {
                continue;
            }

            $parentTransaction = Transaction::withoutGlobalScopes()->find($entry->transaction_id);

            if (! $parentTransaction) {
                continue;
            }

            $income = new Transaction();
            $income->parent_transaction_id = $parentTransaction->id;
            $income->type = TransactionType::Income;
            $income->status = TransactionStatus::Completed;
            $income->concept = $description . ': ' . $parentTransaction->concept;
            $income->amount = $incomeAmount;
            $income->percentage = 100.0;
            $income->account_id = $account->id;
            $income->user_id = $entry->user_id;
            $income->paid_by_user_id = null;
            $income->custodian_user_id = null;
            $income->payment_source = null;
            $income->scheduled_at = $occurredAt;
            $income->financial_goal_id = null;
            $income->legacy_migrated_at = Carbon::now();
            $income->save();

            $createdTotal = round($createdTotal + $incomeAmount, 2);
            $remainingDeficit = round($remainingDeficit - $incomeAmount, 2);
        }

        if ($createdTotal > 0.0) {
            $account->newQuery()
                ->whereKey($account->id)
                ->increment('balance', $createdTotal);
        }
    }
}
