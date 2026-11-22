<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Tests;

use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MySqlExactMoneyTest extends TestCase
{
    public function testDriverAndAggregatesPreserveEveryCent(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('Exact DECIMAL persistence requires the dedicated MySQL test profile.');
        }

        Schema::create('exact_money_probes', static function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 19, 2);
        });
        DB::table('exact_money_probes')->insert([
            ['amount' => '900719925474099.91'],
            ['amount' => '0.01'],
            ['amount' => '-0.01'],
        ]);

        $first = DB::table('exact_money_probes')->orderBy('id')->value('amount');
        $sum = DB::table('exact_money_probes')->sum('amount');
        $rows = DB::table('exact_money_probes')->orderBy('id')->pluck('amount');

        self::assertIsString($first);
        self::assertSame('900719925474099.91', $first);
        self::assertIsString($sum);
        self::assertSame('900719925474099.91', $sum);
        self::assertSame($sum, Decimal::sum($rows));
        self::assertSame('900719925474099.92', Decimal::add($first, '0.01'));
    }
}
