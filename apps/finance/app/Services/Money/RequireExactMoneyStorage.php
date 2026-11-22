<?php

declare(strict_types=1);

namespace App\Services\Money;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class RequireExactMoneyStorage
{
    public function execute(): void
    {
        if ($this->verified) {
            return;
        }
        if ('mysql' !== DB::getDriverName()) {
            throw new RuntimeException('Financial operations require MySQL exact decimal storage.');
        }
        foreach (AuditExactMoneyStorage::COLUMNS as $table => $column) {
            if (! Schema::hasColumn($table, $column) || Schema::hasColumn($table, $column . '_exact')
                || 'decimal' !== Schema::getColumnType($table, $column)) {
                throw new RuntimeException('Exact monetary storage is pending. Keep writers stopped, review money:storage and activate the approved conversion.');
            }
        }
        $this->verified = true;
    }

    private bool $verified = false;
}
