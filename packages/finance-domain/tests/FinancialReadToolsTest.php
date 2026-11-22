<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Tests;

use function app;
use function array_column;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\ReadModels\BuildDashboardPeriodSummary;
use FinSi\FinanceDomain\ReadModels\FinancialReadTools;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinancialReadToolsTest extends TestCase
{
    public function testBalancesOnlyIncludeVisibleAccountsAndRevalidateMembership(): void
    {
        $result = $this->tool('list_account_balances');
        self::assertCount(1, $result['data']);
        self::assertSame('123.45', $result['data'][0]['balance']);
        DB::table('account_user')->where('user_id', $this->actor)->delete();
        $this->expectException(ModelNotFoundException::class);
        $this->tool('get_account_balance', ['account_id' => $this->account]);
    }

    public function testExpenseTotalsMatchDashboardAndAreNotPageTotals(): void
    {
        $result = $this->tool('list_expenses', [...$this->period(), 'limit' => 1]);
        self::assertSame('30.35', $result['data']['total']);
        self::assertCount(1, $result['data']['items']);
        self::assertFalse($result['complete']);
        self::assertSame('1', $result['next_cursor']);
        $summary = app(BuildDashboardPeriodSummary::class)->execute($this->actor, '2026-01-01', '2026-01-31');
        self::assertSame('30.35', $summary['outcome_total']);
        self::assertSame('30.35', $this->tool('summarize_expenses', $this->period())['data']['total']);
    }

    public function testInferredGroupsRecalculateAmountsAndRejectForeignEvidence(): void
    {
        $result = $this->tool('aggregate_expense_groups', [...$this->period(), 'groups' => [['label' => 'Food', 'transaction_ids' => [1, 2]]]]);
        self::assertSame('30.35', $result['data'][0]['amount']);
        self::assertSame('inferred', $result['data'][0]['classification']);
        $this->expectException(ValidationException::class);
        $this->tool('aggregate_expense_groups', [...$this->period(), 'groups' => [['label' => 'Food', 'transaction_ids' => [3]]]]);
    }

    public function testDuplicateEvidenceIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool('aggregate_expense_groups', [...$this->period(), 'groups' => [['label' => 'Food', 'transaction_ids' => [1]], ['label' => 'Other', 'transaction_ids' => [1]]]]);
    }

    public function testPayablesAndBreakdownReconcileWithoutInventingAttribution(): void
    {
        $creditor = User::query()->forceCreate(['name' => 'Creditor', 'email' => 'creditor@example.test'])->id;
        DB::table('account_user')->insert(['account_id' => $this->account, 'user_id' => $creditor]);
        DB::table('account_member_ledger_entries')->insert([
            ['account_id' => $this->account, 'user_id' => $this->actor, 'type' => 'legacy_settlement', 'amount' => -25],
            ['account_id' => $this->account, 'user_id' => $creditor, 'type' => 'legacy_settlement', 'amount' => 25],
        ]);
        $summary = $this->tool('get_payables_summary');
        $detail = $this->tool('get_payable_breakdown', ['account_id' => $this->account]);
        self::assertSame('25.00', $summary['data']['total']);
        self::assertSame('25.00', $detail['data']['total']);
        self::assertSame('adjustment', $detail['data']['items'][0]['kind']);
        self::assertNull($detail['data']['items'][0]['transaction_id']);
        self::assertSame('25.00', $detail['data']['items'][0]['amount']);
    }

    public function testGlobalCategoriesIncludeOnlyVisibleSharedCatalogsWithReferences(): void
    {
        Account::query()->whereKey($this->account)->update(['uses_shared_categories' => true]);
        DB::table('categories')->insert([
            ['id' => 1, 'user_id' => $this->actor, 'account_id' => null, 'name' => 'Personal'],
            ['id' => 2, 'user_id' => $this->actor, 'account_id' => $this->account, 'name' => 'Shared'],
            ['id' => 3, 'user_id' => 2, 'account_id' => $this->foreign, 'name' => 'Private'],
        ]);
        $result = $this->tool('find_categories');
        self::assertSame(['Personal', 'Shared'], array_column($result['data'], 'name'));
        self::assertSame([['account_id' => (string) $this->account]], $result['references']);
    }

    public function testVirtualAccountsDoNotDuplicateConsolidatedExpenses(): void
    {
        Account::query()->whereKey($this->account)->update(['virtual' => true]);
        self::assertSame('0.00', $this->tool('summarize_expenses', $this->period())['data']['total']);
        self::assertSame('30.35', $this->tool('summarize_expenses', [...$this->period(), 'account_ids' => [$this->account]])['data']['total']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::query()->forceCreate(['name' => 'Owner', 'email' => 'owner@example.test'])->id;
        $other = User::query()->forceCreate(['name' => 'Other', 'email' => 'other@example.test'])->id;
        $this->account = Account::query()->forceCreate(['name' => 'House', 'user_id' => $this->actor, 'balance' => 123.45])->id;
        $this->foreign = Account::query()->forceCreate(['name' => 'Private', 'user_id' => $other, 'balance' => 999])->id;
        DB::table('account_user')->insert([['account_id' => $this->account, 'user_id' => $this->actor], ['account_id' => $this->foreign, 'user_id' => $other]]);
        DB::table('transactions')->insert([
            ['id' => 1, 'account_id' => $this->account, 'user_id' => $this->actor, 'amount' => 10.25, 'type' => 'outcome', 'status' => 'completed', 'scheduled_at' => '2026-01-15', 'parent_transaction_id' => null],
            ['id' => 2, 'account_id' => $this->account, 'user_id' => $this->actor, 'amount' => 20.10, 'type' => 'outcome', 'status' => 'completed', 'scheduled_at' => '2026-01-20', 'parent_transaction_id' => null],
            ['id' => 3, 'account_id' => $this->foreign, 'user_id' => $other, 'amount' => 999, 'type' => 'outcome', 'status' => 'completed', 'scheduled_at' => '2026-01-20', 'parent_transaction_id' => null],
            ['id' => 4, 'account_id' => $this->account, 'user_id' => $this->actor, 'amount' => 500, 'type' => 'outcome', 'status' => 'pending', 'scheduled_at' => '2026-01-20', 'parent_transaction_id' => null],
            ['id' => 5, 'account_id' => $this->account, 'user_id' => $this->actor, 'amount' => 100, 'type' => 'outcome', 'status' => 'completed', 'scheduled_at' => '2026-01-20', 'parent_transaction_id' => 1],
        ]);
    }

    private int $actor;

    private int $account;

    private int $foreign;

    private function tool(string $tool, array $arguments = []): array
    {
        return app(FinancialReadTools::class)->execute($this->actor, $tool, $arguments);
    }

    private function period(): array
    {
        return ['start_date' => '2026-01-01', 'end_date' => '2026-01-31'];
    }
}
