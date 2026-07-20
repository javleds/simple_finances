<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();

        DB::table('transactions')
            ->whereNull('parent_transaction_id')
            ->whereNull('payment_source')
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use ($now): void {
                foreach ($transactions as $transaction) {
                    if ($transaction->type === 'income' && $transaction->status === 'completed') {
                        $this->migrateCompletedIncome($transaction, $now);
                        continue;
                    }

                    if ($transaction->type !== 'outcome' || $transaction->status !== 'completed') {
                        continue;
                    }

                    $children = DB::table('transactions')
                        ->where('parent_transaction_id', $transaction->id)
                        ->orderBy('id')
                        ->get();

                    if ($children->isEmpty()) {
                        $this->migrateSimpleOutcome($transaction, $now);
                        continue;
                    }

                    $this->migrateSplitOutcome($transaction, $children, $now);
                }
            });

        $this->recalculateStandardAccountBalances();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('account_member_ledger_entries')->delete();
        DB::table('transaction_allocations')->delete();
        DB::table('transactions')->update([
            'paid_by_user_id' => null,
            'custodian_user_id' => null,
            'payment_source' => null,
            'legacy_migrated_at' => null,
        ]);
    }

    private function migrateCompletedIncome(object $transaction, Carbon $now): void
    {
        DB::table('transactions')
            ->where('id', $transaction->id)
            ->update([
                'custodian_user_id' => $transaction->user_id,
                'payment_source' => null,
                'updated_at' => $now,
            ]);

        $this->insertLedgerEntry(
            transaction: $transaction,
            userId: (int) $transaction->user_id,
            type: 'income_custody',
            amount: (float) $transaction->amount,
            now: $now,
        );
    }

    private function migrateSimpleOutcome(object $transaction, Carbon $now): void
    {
        DB::table('transactions')
            ->where('id', $transaction->id)
            ->update([
                'paid_by_user_id' => $transaction->user_id,
                'payment_source' => 'account_fund',
                'updated_at' => $now,
            ]);

        $this->insertAllocation($transaction, (int) $transaction->user_id, 100.0, (float) $transaction->amount, $now);
        $this->insertLedgerEntry(
            transaction: $transaction,
            userId: (int) $transaction->user_id,
            type: 'account_fund_expense',
            amount: ((float) $transaction->amount) * -1,
            now: $now,
        );
    }

    private function migrateSplitOutcome(object $transaction, iterable $children, Carbon $now): void
    {
        DB::table('transactions')
            ->where('id', $transaction->id)
            ->update([
                'paid_by_user_id' => $transaction->user_id,
                'payment_source' => 'member_out_of_pocket',
                'updated_at' => $now,
            ]);

        $this->insertLedgerEntry(
            transaction: $transaction,
            userId: (int) $transaction->user_id,
            type: 'expense_paid',
            amount: (float) $transaction->amount,
            now: $now,
        );

        foreach ($this->normalizedChildAllocations($children, (float) $transaction->amount) as $allocation) {
            $child = $allocation['child'];
            $amount = $allocation['amount'];

            $this->insertAllocation(
                transaction: $transaction,
                userId: (int) $child->user_id,
                percentage: $allocation['percentage'],
                amount: $amount,
                now: $now,
            );

            $this->insertLedgerEntry(
                transaction: $transaction,
                userId: (int) $child->user_id,
                type: 'expense_share',
                amount: $amount * -1,
                now: $now,
                relatedUserId: (int) $transaction->user_id,
            );

            if ($child->status === 'completed') {
                $this->insertLedgerEntry(
                    transaction: $transaction,
                    userId: (int) $child->user_id,
                    type: 'settlement_transfer',
                    amount: $amount,
                    now: $now,
                    relatedUserId: (int) $transaction->user_id,
                );
                $this->insertLedgerEntry(
                    transaction: $transaction,
                    userId: (int) $transaction->user_id,
                    type: 'settlement_transfer',
                    amount: $amount * -1,
                    now: $now,
                    relatedUserId: (int) $child->user_id,
                );
            }
        }

        DB::table('transactions')
            ->where('parent_transaction_id', $transaction->id)
            ->update([
                'legacy_migrated_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function normalizedChildAllocations(iterable $children, float $transactionAmount): array
    {
        $children = collect($children)->values();

        if ($children->isEmpty()) {
            return [];
        }

        $amounts = $this->normalizedChildAmounts($children, $transactionAmount);

        return $children
            ->map(fn (object $child, int $index): array => [
                'child' => $child,
                'amount' => $amounts[$index],
                'percentage' => $transactionAmount > 0
                    ? round(($amounts[$index] / $transactionAmount) * 100, 2)
                    : round((float) $child->percentage, 2),
            ])
            ->all();
    }

    private function normalizedChildAmounts(\Illuminate\Support\Collection $children, float $transactionAmount): array
    {
        $amounts = $children
            ->map(fn (object $child): float => round((float) $child->amount, 2))
            ->all();
        $amountTotal = round(array_sum($amounts), 2);

        if ($amountTotal > 0.0) {
            return $this->adjustLastAmount($amounts, $transactionAmount);
        }

        $percentageTotal = round($children->sum(fn (object $child): float => (float) $child->percentage), 2);

        if ($percentageTotal > 0.0) {
            $amounts = $children
                ->map(fn (object $child): float => round($transactionAmount * ((float) $child->percentage / $percentageTotal), 2))
                ->all();

            return $this->adjustLastAmount($amounts, $transactionAmount);
        }

        $equalAmount = round($transactionAmount / $children->count(), 2);

        return $this->adjustLastAmount(array_fill(0, $children->count(), $equalAmount), $transactionAmount);
    }

    private function adjustLastAmount(array $amounts, float $transactionAmount): array
    {
        $lastIndex = array_key_last($amounts);

        if ($lastIndex === null) {
            return [];
        }

        $delta = round($transactionAmount - array_sum($amounts), 2);
        $amounts[$lastIndex] = round($amounts[$lastIndex] + $delta, 2);

        return $amounts;
    }

    private function insertAllocation(object $transaction, int $userId, float $percentage, float $amount, Carbon $now): void
    {
        DB::table('transaction_allocations')->updateOrInsert(
            [
                'transaction_id' => $transaction->id,
                'user_id' => $userId,
            ],
            [
                'percentage' => round($percentage, 2),
                'amount' => round($amount, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function insertLedgerEntry(
        object $transaction,
        int $userId,
        string $type,
        float $amount,
        Carbon $now,
        ?int $relatedUserId = null,
    ): void {
        DB::table('account_member_ledger_entries')->insert([
            'account_id' => $transaction->account_id,
            'user_id' => $userId,
            'transaction_id' => $transaction->id,
            'related_user_id' => $relatedUserId,
            'type' => $type,
            'amount' => round($amount, 2),
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function recalculateStandardAccountBalances(): void
    {
        DB::table('accounts')
            ->where('credit_card', false)
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $income = (float) DB::table('transactions')
                        ->where('account_id', $account->id)
                        ->whereNull('legacy_migrated_at')
                        ->where('type', 'income')
                        ->where('status', 'completed')
                        ->sum('amount');
                    $outcome = (float) DB::table('transactions')
                        ->where('account_id', $account->id)
                        ->whereNull('legacy_migrated_at')
                        ->where('type', 'outcome')
                        ->where('status', 'completed')
                        ->sum('amount');

                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->update(['balance' => round($income - $outcome, 2)]);
                }
            });
    }
};
