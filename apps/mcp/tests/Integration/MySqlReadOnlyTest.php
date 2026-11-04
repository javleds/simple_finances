<?php

declare(strict_types=1);

namespace Tests\Integration;

use function app;
use function config;

use FinSi\FinanceDomain\ReadModels\FinancialReadTools;

use function getenv;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

use function str_starts_with;
use function strtolower;

use Tests\TestCase;

class MySqlReadOnlyTest extends TestCase
{
    public function testFinancialReadsWorkAndMysqlRejectsEveryWriteClass(): void
    {
        if (! getenv('MCP_TEST_MYSQL_PORT')) {
            self::markTestSkipped('Requires disposable MySQL fixtures and a SELECT-only finance_mcp account.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'driver'   => 'mysql', 'host' => '127.0.0.1', 'port' => getenv('MCP_TEST_MYSQL_PORT'),
            'database' => 'finance', 'username' => 'finance_mcp', 'password' => 'read-only-test',
            'charset'  => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ]]);
        DB::purge('mysql');
        $tools = app(FinancialReadTools::class);
        self::assertSame('REPEATABLE-READ', DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level);
        $snapshotChecks = 0;
        DB::listen(function ($query) use (&$snapshotChecks): void {
            if (str_starts_with(strtolower($query->sql), 'select') && DB::transactionLevel() > 0) {
                ++$snapshotChecks;
            }
        });
        self::assertSame('123.45', $tools->execute(1, 'get_account_balance', ['account_id' => 1])['data']['balance']);
        foreach (['month', 'account', 'category', 'concept'] as $groupBy) {
            self::assertSame('30.35', $tools->execute(1, 'summarize_expenses', ['start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'group_by' => $groupBy])['data']['total']);
        }
        self::assertGreaterThan(5, $snapshotChecks);
        foreach ([
            "UPDATE users SET name = 'Denied' WHERE id = 1",
            'DELETE FROM transactions WHERE id = 1',
            "INSERT INTO accounts (name,user_id) VALUES ('Denied',1)",
            'CREATE TABLE denied_mcp_write (id INT)',
        ] as $statement) {
            try {
                DB::statement($statement);
                self::fail('MySQL permitted a financial write.');
            } catch (QueryException $exception) {
                self::assertSame(1142, $exception->errorInfo[1]);
            }
        }
    }
}
