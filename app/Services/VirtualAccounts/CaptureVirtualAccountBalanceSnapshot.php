<?php

namespace App\Services\VirtualAccounts;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\Transaction;
use App\Services\Accounts\RecalculateAccountBalance;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CaptureVirtualAccountBalanceSnapshot
{
    public function __construct(
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
    ) {}

    public function create(
        Account $account,
        int $userId,
        float $observedBalance,
        string|CarbonInterface $observedAt,
        ?string $notes = null,
    ): AccountBalanceSnapshot {
        $this->ensureSupportedVirtualAccount($account);

        return DB::transaction(function () use ($account, $userId, $observedBalance, $observedAt, $notes): AccountBalanceSnapshot {
            $previousBalance = round((float) $account->balance, 2);
            $delta = round($observedBalance - $previousBalance, 2);
            $snapshot = $this->createSnapshot(
                account: $account,
                userId: $userId,
                observedBalance: $observedBalance,
                previousBalance: $previousBalance,
                delta: $delta,
                observedAt: $observedAt,
                notes: $notes,
            );

            $adjustment = $this->createAdjustmentTransaction($snapshot, $userId);

            if ($adjustment) {
                $snapshot->adjustment_transaction_id = $adjustment->id;
                $snapshot->save();
            }

            $this->recalculateAccountBalance->execute($account);

            return $snapshot->fresh(['adjustmentTransaction']);
        });
    }

    public function update(
        AccountBalanceSnapshot $snapshot,
        float $observedBalance,
        string|CarbonInterface $observedAt,
        ?string $notes = null,
    ): AccountBalanceSnapshot {
        $account = $snapshot->account()->withoutGlobalScopes()->firstOrFail();
        $this->ensureSupportedVirtualAccount($account);
        $this->ensureLatestSnapshot($snapshot);

        return DB::transaction(function () use ($snapshot, $account, $observedBalance, $observedAt, $notes): AccountBalanceSnapshot {
            $this->deleteAdjustmentTransaction($snapshot);
            $this->recalculateAccountBalance->execute($account);

            $previousBalance = round((float) $account->fresh()->balance, 2);
            $delta = round($observedBalance - $previousBalance, 2);

            $snapshot->observed_balance = $observedBalance;
            $snapshot->previous_balance = $previousBalance;
            $snapshot->delta = $delta;
            $snapshot->observed_at = $this->parseDate($observedAt);
            $snapshot->notes = $notes;
            $snapshot->adjustment_transaction_id = null;
            $snapshot->save();

            $adjustment = $this->createAdjustmentTransaction($snapshot, $snapshot->user_id);

            if ($adjustment) {
                $snapshot->adjustment_transaction_id = $adjustment->id;
                $snapshot->save();
            }

            $this->recalculateAccountBalance->execute($account);

            return $snapshot->fresh(['adjustmentTransaction']);
        });
    }

    public function delete(AccountBalanceSnapshot $snapshot): void
    {
        $account = $snapshot->account()->withoutGlobalScopes()->firstOrFail();
        $this->ensureSupportedVirtualAccount($account);
        $this->ensureLatestSnapshot($snapshot);

        DB::transaction(function () use ($snapshot, $account): void {
            $this->deleteAdjustmentTransaction($snapshot);
            $snapshot->delete();
            $this->recalculateAccountBalance->execute($account);
        });
    }

    private function createSnapshot(
        Account $account,
        int $userId,
        float $observedBalance,
        float $previousBalance,
        float $delta,
        string|CarbonInterface $observedAt,
        ?string $notes,
    ): AccountBalanceSnapshot {
        return AccountBalanceSnapshot::query()->create([
            'account_id' => $account->id,
            'user_id' => $userId,
            'observed_balance' => $observedBalance,
            'previous_balance' => $previousBalance,
            'delta' => $delta,
            'observed_at' => $this->parseDate($observedAt),
            'notes' => $notes,
        ]);
    }

    private function createAdjustmentTransaction(AccountBalanceSnapshot $snapshot, int $userId): ?Transaction
    {
        if (round((float) $snapshot->delta, 2) === 0.0) {
            return null;
        }

        $transaction = new Transaction;
        $transaction->type = $snapshot->delta > 0 ? TransactionType::Income : TransactionType::Outcome;
        $transaction->status = TransactionStatus::Completed;
        $transaction->concept = $snapshot->delta > 0
            ? 'Rendimiento observado'
            : 'Ajuste observado de saldo';
        $transaction->amount = abs((float) $snapshot->delta);
        $transaction->percentage = 100.0;
        $transaction->account_id = $snapshot->account_id;
        $transaction->user_id = $userId;
        $transaction->custodian_user_id = $snapshot->delta > 0 ? $userId : null;
        $transaction->paid_by_user_id = $snapshot->delta < 0 ? $userId : null;
        $transaction->payment_source = null;
        $transaction->scheduled_at = $snapshot->observed_at;
        $transaction->account_balance_snapshot_id = $snapshot->id;
        $transaction->save();

        return $transaction;
    }

    private function deleteAdjustmentTransaction(AccountBalanceSnapshot $snapshot): void
    {
        if (! $snapshot->adjustment_transaction_id) {
            return;
        }

        Transaction::withoutGlobalScopes()
            ->where('id', $snapshot->adjustment_transaction_id)
            ->where('account_balance_snapshot_id', $snapshot->id)
            ->delete();
    }

    private function ensureSupportedVirtualAccount(Account $account): void
    {
        abort_unless($account->virtual, 422, 'Only virtual accounts can receive balance snapshots.');
        abort_if($account->credit_card, 422, 'Credit accounts cannot receive balance snapshots.');
        abort_if($account->users()->count() > 1, 422, 'Shared accounts cannot receive balance snapshots.');
    }

    private function ensureLatestSnapshot(AccountBalanceSnapshot $snapshot): void
    {
        $latestSnapshot = AccountBalanceSnapshot::query()
            ->where('account_id', $snapshot->account_id)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->first();

        abort_unless(
            $latestSnapshot?->id === $snapshot->id,
            422,
            'Only the latest balance snapshot can be changed.',
        );
    }

    private function parseDate(string|CarbonInterface $date): CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date;
        }

        return Carbon::parse($date);
    }
}
