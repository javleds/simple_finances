<?php

declare(strict_types=1);

namespace App\Services\VirtualAccounts;

use function abort_if;
use function abort_unless;

use App\Services\Accounts\RecalculateAccountBalance;
use Carbon\CarbonInterface;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountBalanceSnapshot;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaptureVirtualAccountBalanceSnapshot
{
    public function __construct(
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
    ) {
    }

    public function create(
        Account $account,
        int $userId,
        string $observedBalance,
        string | CarbonInterface $observedAt,
        ?string $notes = null,
    ): AccountBalanceSnapshot {
        return DB::transaction(function () use ($account, $userId, $observedBalance, $observedAt, $notes): AccountBalanceSnapshot {
            $account = Account::withoutGlobalScopes()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->ensureSupportedVirtualAccount($account);
            $previousBalance = Decimal::normalize($account->balance);
            $delta = Decimal::subtract($observedBalance, $previousBalance);
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
        string $observedBalance,
        string | CarbonInterface $observedAt,
        ?string $notes = null,
    ): AccountBalanceSnapshot {
        $accountId = AccountBalanceSnapshot::query()->whereKey($snapshot->id)->firstOrFail(['id', 'account_id'])->account_id;

        return DB::transaction(function () use ($snapshot, $accountId, $observedBalance, $observedAt, $notes): AccountBalanceSnapshot {
            $account = Account::withoutGlobalScopes()->whereKey($accountId)->lockForUpdate()->firstOrFail();
            $snapshot = AccountBalanceSnapshot::query()->where('account_id', $accountId)->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $this->ensureSupportedVirtualAccount($account);
            $this->ensureLatestSnapshot($snapshot);
            $this->deleteAdjustmentTransaction($snapshot);
            $this->recalculateAccountBalance->execute($account);

            $previousBalance = Decimal::normalize($account->fresh()->balance);
            $delta = Decimal::subtract($observedBalance, $previousBalance);

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
        $accountId = AccountBalanceSnapshot::query()->whereKey($snapshot->id)->firstOrFail(['id', 'account_id'])->account_id;

        DB::transaction(function () use ($snapshot, $accountId): void {
            $account = Account::withoutGlobalScopes()->whereKey($accountId)->lockForUpdate()->firstOrFail();
            $snapshot = AccountBalanceSnapshot::query()->where('account_id', $accountId)->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $this->ensureSupportedVirtualAccount($account);
            $this->ensureLatestSnapshot($snapshot);
            $this->deleteAdjustmentTransaction($snapshot);
            $snapshot->delete();
            $this->recalculateAccountBalance->execute($account);
        });
    }

    private function createSnapshot(
        Account $account,
        int $userId,
        string $observedBalance,
        string $previousBalance,
        string $delta,
        string | CarbonInterface $observedAt,
        ?string $notes,
    ): AccountBalanceSnapshot {
        return AccountBalanceSnapshot::query()->create([
            'account_id'       => $account->id,
            'user_id'          => $userId,
            'observed_balance' => $observedBalance,
            'previous_balance' => $previousBalance,
            'delta'            => $delta,
            'observed_at'      => $this->parseDate($observedAt),
            'notes'            => $notes,
        ]);
    }

    private function createAdjustmentTransaction(AccountBalanceSnapshot $snapshot, int $userId): ?Transaction
    {
        if (0 === Decimal::compare(Decimal::normalize($snapshot->delta), '0.00')) {
            return null;
        }

        if (Decimal::compare(Decimal::abs($snapshot->delta), '99999999.99') > 0) {
            throw ValidationException::withMessages(['observed_balance' => 'The resulting adjustment exceeds the supported transaction range.']);
        }

        $transaction = new Transaction();
        $transaction->type = Decimal::compare($snapshot->delta, 0) > 0 ? TransactionType::Income : TransactionType::Outcome;
        $transaction->status = TransactionStatus::Completed;
        $transaction->concept = Decimal::compare($snapshot->delta, 0) > 0
            ? 'Rendimiento observado'
            : 'Ajuste observado de saldo';
        $transaction->amount = Decimal::abs(Decimal::normalize($snapshot->delta));
        $transaction->percentage = '100.00';
        $transaction->account_id = $snapshot->account_id;
        $transaction->user_id = $userId;
        $transaction->custodian_user_id = Decimal::compare($snapshot->delta, 0) > 0 ? $userId : null;
        $transaction->paid_by_user_id = Decimal::compare($snapshot->delta, 0) < 0 ? $userId : null;
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
            ->lockForUpdate()
            ->first();

        abort_unless(
            $latestSnapshot?->id === $snapshot->id,
            422,
            'Only the latest balance snapshot can be changed.',
        );
    }

    private function parseDate(string | CarbonInterface $date): CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date;
        }

        return Carbon::parse($date);
    }
}
