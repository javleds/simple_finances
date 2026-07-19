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
        $accountIds = [];

        DB::table('transactions')
            ->where('status', 'pending')
            ->whereNull('legacy_migrated_at')
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use ($now, &$accountIds): void {
                foreach ($transactions as $transaction) {
                    $accountIds[] = (int) $transaction->account_id;

                    DB::table('account_member_ledger_entries')
                        ->where('transaction_id', $transaction->id)
                        ->delete();

                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update($this->completedTransactionPayload($transaction, $now));

                    $completedTransaction = DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->first();

                    if ($completedTransaction === null) {
                        continue;
                    }

                    $this->ensureOutcomeAllocations($completedTransaction, $now);
                    $this->insertLedgerEntries($completedTransaction, $now);
                }
            });

        $this->recalculateAccountBalances(array_values(array_unique($accountIds)));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data normalization migration. Reverting would require knowing which
        // transactions were intentionally completed after deployment.
    }

    private function completedTransactionPayload(object $transaction, Carbon $now): array
    {
        $payload = [
            'status' => 'completed',
            'updated_at' => $now,
        ];

        if ($transaction->type === 'income' && $transaction->custodian_user_id === null) {
            $payload['custodian_user_id'] = $transaction->user_id;
        }

        if ($transaction->type === 'outcome') {
            if ($transaction->paid_by_user_id === null) {
                $payload['paid_by_user_id'] = $transaction->user_id;
            }

            if ($transaction->payment_source === null) {
                $payload['payment_source'] = 'account_fund';
            }
        }

        return $payload;
    }

    private function ensureOutcomeAllocations(object $transaction, Carbon $now): void
    {
        if ($transaction->type !== 'outcome') {
            DB::table('transaction_allocations')
                ->where('transaction_id', $transaction->id)
                ->delete();

            return;
        }

        $hasAllocations = DB::table('transaction_allocations')
            ->where('transaction_id', $transaction->id)
            ->exists();

        if ($hasAllocations) {
            return;
        }

        DB::table('transaction_allocations')->insert([
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->paid_by_user_id ?? $transaction->user_id,
            'percentage' => 100.0,
            'amount' => round((float) $transaction->amount, 2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertLedgerEntries(object $transaction, Carbon $now): void
    {
        if ($transaction->type === 'income') {
            $this->insertLedgerEntry($transaction, $transaction->custodian_user_id ?? $transaction->user_id, 'income_custody', (float) $transaction->amount, $now);

            return;
        }

        if ($transaction->type !== 'outcome') {
            return;
        }

        $paymentSource = $transaction->payment_source ?? 'account_fund';
        $paidByUserId = $transaction->paid_by_user_id ?? $transaction->user_id;

        if ($paymentSource === 'account_fund') {
            $this->insertLedgerEntry($transaction, $paidByUserId, 'account_fund_expense', ((float) $transaction->amount) * -1, $now);

            return;
        }

        $this->insertLedgerEntry($transaction, $paidByUserId, 'expense_paid', (float) $transaction->amount, $now);

        $allocations = DB::table('transaction_allocations')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get();

        foreach ($allocations as $allocation) {
            $this->insertLedgerEntry(
                transaction: $transaction,
                userId: (int) $allocation->user_id,
                type: 'expense_share',
                amount: ((float) $allocation->amount) * -1,
                now: $now,
                relatedUserId: (int) $paidByUserId,
            );
        }
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

    private function recalculateAccountBalances(array $accountIds): void
    {
        if ($accountIds === []) {
            return;
        }

        DB::table('accounts')
            ->whereIn('id', $accountIds)
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $income = $this->completedTransactionTotal($account->id, 'income');
                    $outcome = $this->completedTransactionTotal($account->id, 'outcome');
                    $balance = round($income - $outcome, 2);

                    if (! $account->credit_card) {
                        DB::table('accounts')
                            ->where('id', $account->id)
                            ->update(['balance' => $balance]);

                        continue;
                    }

                    $cutoffIncome = $this->completedTransactionTotal($account->id, 'income', $account->next_cutoff_date);
                    $cutoffOutcome = $this->completedTransactionTotal($account->id, 'outcome', $account->next_cutoff_date);
                    $cutoffBalance = round($cutoffIncome - $cutoffOutcome, 2);
                    $availableCredit = ((float) $account->credit_line) - ($balance * -1);

                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->update([
                            'balance' => $cutoffBalance,
                            'spent' => $balance,
                            'available_credit' => round($availableCredit, 2),
                        ]);
                }
            });
    }

    private function completedTransactionTotal(int $accountId, string $type, ?string $until = null): float
    {
        $query = DB::table('transactions')
            ->where('account_id', $accountId)
            ->whereNull('legacy_migrated_at')
            ->where('type', $type)
            ->where('status', 'completed');

        if ($until !== null) {
            $query->where('scheduled_at', '<=', $until);
        }

        return (float) $query->sum('amount');
    }
};
