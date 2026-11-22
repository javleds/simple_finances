<?php

declare(strict_types=1);

namespace Tests\Integration;

use function app;

use App\Services\Money\ActivateExactMoneyStorage;
use App\Services\Money\AuditExactMoneyStorage;

use function database_path;

use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

use function str_ends_with;

use Tests\TestCase;

final class ExactMoneyStorageTest extends TestCase
{
    public function testReviewedConversionPreservesLegacyAndExactValues(): void
    {
        $id = $this->account('12.345');
        $audit = app(AuditExactMoneyStorage::class)->execute();
        self::assertSame('12.35', $audit['columns']['accounts']['differences'][0]['proposed']);
        self::assertFalse($audit['blocked']);
        $this->artisan('down')->assertSuccessful();
        app(ActivateExactMoneyStorage::class)->execute($audit['approval']);
        self::assertTrue(Schema::hasColumn('accounts', 'spent_legacy'));
        self::assertSame('12.35', DB::table('accounts')->where('id', $id)->value('spent'));
        self::assertSame('12.345', DB::table('accounts')->where('id', $id)->selectRaw('CAST(spent_legacy AS CHAR) AS original')->first()->original);
        $again = app(AuditExactMoneyStorage::class)->execute();
        self::assertSame('active', $again['columns']['accounts']['status']);
        self::assertSame($again, app(ActivateExactMoneyStorage::class)->execute($again['approval']));
    }

    public function testChangedDataInvalidatesApprovalWithoutSwitchingColumns(): void
    {
        $id = $this->account('10.25');
        $audit = app(AuditExactMoneyStorage::class)->execute();
        DB::table('accounts')->where('id', $id)->update(['spent' => '10.26']);
        $this->artisan('down')->assertSuccessful();

        try {
            app(ActivateExactMoneyStorage::class)->execute($audit['approval']);
            self::fail('A stale approval must not activate storage.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('approval hash', $exception->getMessage());
        }
        self::assertTrue(Schema::hasColumn('accounts', 'spent_exact'));
        self::assertNull(DB::table('accounts')->where('id', $id)->value('spent_exact'));
    }

    public function testConversionRequiresMaintenanceAndPreservesNull(): void
    {
        $id = $this->account(null);
        $audit = app(AuditExactMoneyStorage::class)->execute();

        try {
            app(ActivateExactMoneyStorage::class)->execute($audit['approval']);
            self::fail('Activation must require maintenance.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('maintenance', $exception->getMessage());
        }
        $this->artisan('down')->assertSuccessful();
        app(ActivateExactMoneyStorage::class)->execute($audit['approval']);
        self::assertNull(DB::table('accounts')->where('id', $id)->value('spent'));
    }

    public function testOutOfRangeValuesBlockConversion(): void
    {
        $this->account('100000000000000000.00');
        $audit = app(AuditExactMoneyStorage::class)->execute();
        self::assertTrue($audit['blocked']);
        $this->artisan('down')->assertSuccessful();
        $this->expectException(RuntimeException::class);
        app(ActivateExactMoneyStorage::class)->execute($audit['approval']);
    }

    public function testFloatConversionDoesNotLoseDigitsThroughMysqlTextFormatting(): void
    {
        DB::statement('ALTER TABLE shared_transaction_notification_items CHANGE COLUMN amount amount_exact DECIMAL(19,2) NULL, CHANGE COLUMN amount_legacy amount FLOAT NULL');
        $id = DB::table('shared_transaction_notification_items')->insertGetId([
            'batch_id' => 1, 'modifier_id' => 1, 'action' => 'created', 'concept' => 'Legacy amount',
            'type'     => 'outcome', 'amount' => '123456.78', 'scheduled_at' => '2026-01-01 00:00:00',
        ]);
        $audit = app(AuditExactMoneyStorage::class)->execute();
        self::assertSame('123456.78', $audit['columns']['shared_transaction_notification_items']['differences'][0]['proposed']);
        $this->artisan('down')->assertSuccessful();
        app(ActivateExactMoneyStorage::class)->execute($audit['approval']);
        self::assertSame('123456.78', DB::table('shared_transaction_notification_items')->where('id', $id)->value('amount'));
        self::assertSame('float', Schema::getColumnType('shared_transaction_notification_items', 'amount_legacy'));
    }

    public function testPartialAdditiveMigrationCanBeResumed(): void
    {
        $this->account('1.25');
        $migration = require database_path('migrations/2026_11_22_131811_add_exact_money_shadow_columns.php');
        $migration->up();
        self::assertTrue(Schema::hasColumn('accounts', 'spent_exact'));
        self::assertTrue(Schema::hasColumn('shared_transaction_notification_items', 'amount_legacy'));
        $this->artisan('money:storage', ['--check' => true])->assertFailed();
    }

    public function testNonHttpAccountWritesStayBlockedWhileStorageIsPending(): void
    {
        $id = $this->account('1.25');
        $account = \FinSi\FinanceDomain\Models\Account::withoutGlobalScopes()->findOrFail($id);

        try {
            $account->update(['name' => 'Must not persist']);
            self::fail('Writers must remain blocked until exact storage is active.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('pending', $exception->getMessage());
        }
        self::assertSame('Storage test', DB::table('accounts')->where('id', $id)->value('name'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        if ('mysql' !== DB::getDriverName()) {
            self::markTestSkipped('Exact storage migration requires disposable MySQL.');
        }
        if (! str_ends_with(DB::getDatabaseName(), '_test')) {
            throw new RuntimeException('Storage migration tests require a disposable *_test database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        DB::statement('ALTER TABLE accounts CHANGE COLUMN spent spent_exact DECIMAL(19,2) NULL, CHANGE COLUMN spent_legacy spent DOUBLE NULL');
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            $this->artisan('up');
        }
        parent::tearDown();
    }

    private function account(?string $spent): int
    {
        return DB::table('accounts')->insertGetId([
            'name'    => 'Storage test', 'user_id' => User::factory()->create()->id,
            'balance' => '0.00', 'spent' => $spent,
        ]);
    }
}
