<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const JAVIER_ID = 1;
    private const DIVANNY_ID = 3;

    private const ACCOUNT_HOME_SWEET_HOME = 10;
    private const ACCOUNT_KALI = 14;
    private const ACCOUNT_NUESTROS_GASTOS = 19;
    private const ACCOUNT_MINI_US = 20;
    private const ACCOUNT_RETIREMENT_JAVI = 21;
    private const ACCOUNT_RETIREMENT_DIV = 22;
    private const ACCOUNT_YPONE = 24;

    private const CUSTODIAN_BY_ACCOUNT = [
        self::ACCOUNT_HOME_SWEET_HOME => self::DIVANNY_ID,
        self::ACCOUNT_KALI => self::JAVIER_ID,
        self::ACCOUNT_MINI_US => self::JAVIER_ID,
        self::ACCOUNT_RETIREMENT_JAVI => self::JAVIER_ID,
        self::ACCOUNT_RETIREMENT_DIV => self::DIVANNY_ID,
        self::ACCOUNT_YPONE => self::DIVANNY_ID,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();

        $this->convertMiniUsDrAbrahamExpenses($now);
        $this->normalizeCurrentAccountCustody($now);
        $this->normalizeNuestrosGastos($now);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration documents legacy data corrections. Reverting would require
        // knowing which reimbursements and custody transfers were changed afterwards.
    }

    private function convertMiniUsDrAbrahamExpenses(Carbon $now): void
    {
        DB::table('transactions')
            ->where('account_id', self::ACCOUNT_MINI_US)
            ->where('type', 'outcome')
            ->where('status', 'completed')
            ->whereIn('concept', [
                'Dr. Abraham 2-junio',
                'Dr. Abraham 2-jul',
            ])
            ->orderBy('id')
            ->get()
            ->each(function (object $transaction) use ($now): void {
                $amount = round((float) $transaction->amount, 2);

                DB::table('account_member_ledger_entries')
                    ->where('transaction_id', $transaction->id)
                    ->delete();
                DB::table('transaction_allocations')
                    ->where('transaction_id', $transaction->id)
                    ->delete();

                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'paid_by_user_id' => self::DIVANNY_ID,
                        'payment_source' => 'member_out_of_pocket',
                        'updated_at' => $now,
                    ]);

                DB::table('transaction_allocations')->insert([
                    'transaction_id' => $transaction->id,
                    'user_id' => self::JAVIER_ID,
                    'percentage' => 100.0,
                    'amount' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->insertLedgerEntry(
                    accountId: self::ACCOUNT_MINI_US,
                    userId: self::DIVANNY_ID,
                    transactionId: (int) $transaction->id,
                    type: 'expense_paid',
                    amount: $amount,
                    description: $transaction->concept,
                    occurredAt: $transaction->scheduled_at,
                    now: $now,
                );
                $this->insertLedgerEntry(
                    accountId: self::ACCOUNT_MINI_US,
                    userId: self::JAVIER_ID,
                    transactionId: (int) $transaction->id,
                    relatedUserId: self::DIVANNY_ID,
                    type: 'expense_share',
                    amount: $amount * -1,
                    description: $transaction->concept,
                    occurredAt: $transaction->scheduled_at,
                    now: $now,
                );
            });
    }

    private function normalizeCurrentAccountCustody(Carbon $now): void
    {
        foreach (self::CUSTODIAN_BY_ACCOUNT as $accountId => $custodianUserId) {
            $account = DB::table('accounts')->where('id', $accountId)->first();

            if ($account === null) {
                continue;
            }

            $this->normalizeAccountCustody(
                accountId: $accountId,
                desiredAmounts: $this->desiredCustodyAmounts($accountId, $custodianUserId, (float) $account->balance),
                now: $now,
            );
        }
    }

    private function normalizeNuestrosGastos(Carbon $now): void
    {
        $account = DB::table('accounts')
            ->where('id', self::ACCOUNT_NUESTROS_GASTOS)
            ->first();

        if ($account === null) {
            return;
        }

        $this->normalizeAccountCustody(self::ACCOUNT_NUESTROS_GASTOS, [
            self::JAVIER_ID => 0.0,
            self::DIVANNY_ID => 0.0,
        ], $now);

        $pendingAmount = round(abs((float) $account->balance), 2);

        $this->normalizeSettlement(
            accountId: self::ACCOUNT_NUESTROS_GASTOS,
            desiredAmounts: [
                self::JAVIER_ID => $pendingAmount,
                self::DIVANNY_ID => $pendingAmount * -1,
            ],
            now: $now,
        );
    }

    private function desiredCustodyAmounts(int $accountId, int $custodianUserId, float $accountBalance): array
    {
        $userIds = $this->accountUserIds($accountId);
        $amounts = [];

        foreach ($userIds as $userId) {
            $amounts[$userId] = $userId === $custodianUserId
                ? round($accountBalance, 2)
                : 0.0;
        }

        return $amounts;
    }

    private function normalizeAccountCustody(int $accountId, array $desiredAmounts, Carbon $now): void
    {
        foreach ($desiredAmounts as $userId => $desiredAmount) {
            $currentAmount = $this->currentLedgerAmount($accountId, (int) $userId, [
                'income_custody',
                'account_fund_expense',
                'internal_transfer',
                'manual_adjustment',
            ]);
            $delta = round($desiredAmount - $currentAmount, 2);

            if ($delta === 0.0) {
                continue;
            }

            $this->insertLedgerEntry(
                accountId: $accountId,
                userId: (int) $userId,
                transactionId: null,
                type: 'manual_adjustment',
                amount: $delta,
                description: 'Legacy custody normalization',
                occurredAt: $now,
                now: $now,
            );
        }
    }

    private function normalizeSettlement(int $accountId, array $desiredAmounts, Carbon $now): void
    {
        foreach ($desiredAmounts as $userId => $desiredAmount) {
            $currentAmount = $this->currentLedgerAmount($accountId, (int) $userId, [
                'expense_paid',
                'expense_share',
                'settlement_transfer',
                'legacy_settlement',
            ]);
            $delta = round($desiredAmount - $currentAmount, 2);

            if ($delta === 0.0) {
                continue;
            }

            $this->insertLedgerEntry(
                accountId: $accountId,
                userId: (int) $userId,
                transactionId: null,
                type: 'legacy_settlement',
                amount: $delta,
                description: 'Legacy settlement normalization',
                occurredAt: $now,
                now: $now,
            );
        }
    }

    private function currentLedgerAmount(int $accountId, int $userId, array $types): float
    {
        return round((float) DB::table('account_member_ledger_entries')
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->whereIn('type', $types)
            ->sum('amount'), 2);
    }

    private function accountUserIds(int $accountId): array
    {
        return DB::table('account_user')
            ->where('account_id', $accountId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn (int|string $userId): int => (int) $userId)
            ->all();
    }

    private function insertLedgerEntry(
        int $accountId,
        int $userId,
        ?int $transactionId,
        string $type,
        float $amount,
        string $description,
        Carbon|string $occurredAt,
        Carbon $now,
        ?int $relatedUserId = null,
    ): void {
        DB::table('account_member_ledger_entries')->insert([
            'account_id' => $accountId,
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'related_user_id' => $relatedUserId,
            'type' => $type,
            'amount' => round($amount, 2),
            'description' => $description,
            'occurred_at' => $occurredAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
