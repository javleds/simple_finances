<?php

declare(strict_types=1);

namespace App\Services\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

use function hash;
use function hash_final;
use function hash_init;
use function hash_update;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function json_encode;

use const JSON_THROW_ON_ERROR;

use RuntimeException;

final class AuditExactMoneyStorage
{
    public const COLUMNS = ['accounts' => 'spent', 'shared_transaction_notification_items' => 'amount'];

    public function execute(): array
    {
        if ('mysql' !== DB::getDriverName()) {
            throw new RuntimeException('Exact monetary storage must be audited on MySQL.');
        }

        $report = ['database' => DB::getDatabaseName(), 'columns' => [], 'blocked' => false];
        foreach (self::COLUMNS as $table => $column) {
            if (Schema::hasColumn($table, $column . '_legacy')) {
                if (! Schema::hasColumn($table, $column) || Schema::hasColumn($table, $column . '_exact') || 'decimal' !== Schema::getColumnType($table, $column)) {
                    throw new RuntimeException('Incomplete monetary column activation requires recovery before continuing.');
                }
                $report['columns'][$table] = ['status' => 'active'];

                continue;
            }

            if (! Schema::hasColumn($table, $column . '_exact')) {
                throw new RuntimeException('Run the additive exact-money migration before auditing.');
            }

            $checksum = hash_init('sha256');
            $count = 0;
            $differences = [];
            foreach (DB::table($table)->select('id')->selectRaw('CAST(`' . $column . '` AS DECIMAL(65,30)) AS original')->orderBy('id')->lazyById(500) as $row) {
                ++$count;
                $original = $row->original;
                $canonical = null;
                $error = null;
                if (null === $original && 'shared_transaction_notification_items' === $table) {
                    $report['blocked'] = true;
                    $differences[] = ['id' => $row->id, 'original' => null, 'proposed' => null, 'error' => 'null_not_allowed'];
                }
                if (null !== $original) {
                    $decimal = BigDecimal::of($original);
                    $canonical = (string) $decimal->toScale(2, RoundingMode::HALF_UP);
                    if (BigDecimal::of($canonical)->abs()->isGreaterThan('99999999999999999.99')) {
                        $error = 'out_of_range';
                        $report['blocked'] = true;
                    }
                    if (null !== $error || ! $decimal->isEqualTo($canonical)) {
                        $differences[] = ['id' => $row->id, 'original' => $original, 'proposed' => $canonical, 'error' => $error];
                    }
                }
                hash_update($checksum, json_encode([$row->id, $original, $canonical], JSON_THROW_ON_ERROR) . "\n");
            }
            $report['columns'][$table] = ['status' => 'pending', 'rows' => $count, 'checksum' => hash_final($checksum), 'differences' => $differences];
        }
        $report['approval'] = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR));

        return $report;
    }
}
