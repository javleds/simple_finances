<?php

declare(strict_types=1);

namespace App\Services\Money;

use function app;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

use function hash_equals;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ActivateExactMoneyStorage
{
    public function __construct(private readonly AuditExactMoneyStorage $audit)
    {
    }

    public function execute(string $approval): array
    {
        if (! app()->isDownForMaintenance()) {
            throw new RuntimeException('Enable maintenance and stop every financial writer before activation.');
        }

        $report = $this->audit->execute();
        if ($report['blocked'] || ! hash_equals($report['approval'], $approval)) {
            throw new RuntimeException('Review the current audit and provide its approval hash; out-of-range values cannot be converted.');
        }

        foreach (AuditExactMoneyStorage::COLUMNS as $table => $column) {
            if ('active' === $report['columns'][$table]['status']) {
                continue;
            }
            foreach (DB::table($table)->select('id')->selectRaw('CAST(`' . $column . '` AS DECIMAL(65,30)) AS original')->orderBy('id')->lazyById(500) as $row) {
                $exact = null === $row->original ? null : (string) BigDecimal::of($row->original)->toScale(2, RoundingMode::HALF_UP);
                DB::table($table)->where('id', $row->id)->update([$column . '_exact' => $exact]);
            }
        }

        if (! hash_equals($approval, $this->audit->execute()['approval'])) {
            throw new RuntimeException('Financial data changed during conversion; stop writers and repeat the audit.');
        }

        foreach (AuditExactMoneyStorage::COLUMNS as $table => $column) {
            if ('active' === $report['columns'][$table]['status']) {
                continue;
            }
            foreach (DB::table($table)->select('id', $column . '_exact')->selectRaw('CAST(`' . $column . '` AS DECIMAL(65,30)) AS original')->orderBy('id')->lazyById(500) as $row) {
                $expected = null === $row->original ? null : (string) BigDecimal::of($row->original)->toScale(2, RoundingMode::HALF_UP);
                if ($row->{$column . '_exact'} !== $expected) {
                    throw new RuntimeException('Exact-money verification failed before switching columns.');
                }
            }
        }

        foreach (AuditExactMoneyStorage::COLUMNS as $table => $column) {
            if ('active' === $report['columns'][$table]['status']) {
                continue;
            }
            $legacyType = 'accounts' === $table ? 'DOUBLE NULL' : 'FLOAT NULL';
            $exactType = 'accounts' === $table ? 'DECIMAL(19,2) NULL' : 'DECIMAL(19,2) NOT NULL';
            DB::statement('ALTER TABLE `' . $table . '` CHANGE COLUMN `' . $column . '` `' . $column . '_legacy` ' . $legacyType . ', CHANGE COLUMN `' . $column . '_exact` `' . $column . '` ' . $exactType);
        }

        return $this->audit->execute();
    }
}
