<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['accounts' => 'spent', 'shared_transaction_notification_items' => 'amount'] as $name => $column) {
            if (Schema::hasColumn($name, $column . '_legacy')) {
                continue;
            }
            if (! Schema::hasColumn($name, $column . '_exact')) {
                Schema::table($name, static function (Blueprint $table) use ($column): void {
                    $table->decimal($column . '_exact', 19, 2)->nullable();
                });
            }

            if (! DB::table($name)->exists()) {
                if ('mysql' === DB::getDriverName()) {
                    $legacyType = 'accounts' === $name ? 'DOUBLE NULL' : 'FLOAT NULL';
                    $exactType = 'accounts' === $name ? 'DECIMAL(19,2) NULL' : 'DECIMAL(19,2) NOT NULL';
                    DB::statement('ALTER TABLE `' . $name . '` CHANGE COLUMN `' . $column . '` `' . $column . '_legacy` ' . $legacyType . ', CHANGE COLUMN `' . $column . '_exact` `' . $column . '` ' . $exactType);

                    continue;
                }
                Schema::table($name, static function (Blueprint $table) use ($column): void {
                    $table->renameColumn($column, $column . '_legacy');
                    $table->renameColumn($column . '_exact', $column);
                });
                if ('shared_transaction_notification_items' === $name) {
                    Schema::table($name, static function (Blueprint $table): void {
                        $table->decimal('amount', 19, 2)->nullable(false)->change();
                        $table->float('amount_legacy')->nullable()->change();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['accounts' => 'spent', 'shared_transaction_notification_items' => 'amount'] as $name => $column) {
            if (Schema::hasColumn($name, $column . '_legacy')) {
                throw new RuntimeException('Activated exact-money columns require reconciled rollback; legacy columns may be stale.');
            }
        }

        foreach (['accounts' => 'spent', 'shared_transaction_notification_items' => 'amount'] as $name => $column) {
            Schema::table($name, static function (Blueprint $table) use ($column): void {
                $table->dropColumn($column . '_exact');
            });
        }
    }
};
