<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('ALTER TABLE shared_transaction_notification_batches ADD COLUMN group_key VARCHAR NULL');
            DB::statement('CREATE UNIQUE INDEX unique_pending_shared_transaction_group ON shared_transaction_notification_batches (group_key, status)');
        } else {
            Schema::table('shared_transaction_notification_batches', function (Blueprint $table) {
                $table->string('group_key')->nullable()->after('account_id');
                $table->unique(['group_key', 'status'], 'unique_pending_shared_transaction_group');
            });
        }

        if ($driver !== 'sqlite') {
            $this->dropIndexIfExists(
                'shared_transaction_notification_batches',
                'unique_pending_batch_per_user_account',
            );
        }

        if ($driver === 'sqlite') {
            DB::statement('ALTER TABLE shared_transaction_notification_items ADD COLUMN account_id INTEGER NULL');
            DB::statement('CREATE INDEX shared_transaction_notification_items_account_id_index ON shared_transaction_notification_items (account_id)');
        } else {
            Schema::table('shared_transaction_notification_items', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('batch_id');
                $table->index(['account_id']);
            });
        }

        if ($driver === 'sqlite') {
            DB::statement(
                'UPDATE shared_transaction_notification_items
                SET account_id = (
                    SELECT account_id
                    FROM shared_transaction_notification_batches
                    WHERE shared_transaction_notification_batches.id = shared_transaction_notification_items.batch_id
                )'
            );
        } else {
            DB::table('shared_transaction_notification_items')
                ->join('shared_transaction_notification_batches', 'shared_transaction_notification_items.batch_id', '=', 'shared_transaction_notification_batches.id')
                ->update([
                    'shared_transaction_notification_items.account_id' => DB::raw('shared_transaction_notification_batches.account_id'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_transaction_notification_batches', function (Blueprint $table) {
            $table->dropUnique('unique_pending_shared_transaction_group');
            $table->dropColumn('group_key');
        });

        Schema::table('shared_transaction_notification_batches', function (Blueprint $table) {
            $table->unique(['user_id', 'account_id', 'status'], 'unique_pending_batch_per_user_account');
        });

        Schema::table('shared_transaction_notification_items', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = collect(DB::select("PRAGMA index_list('{$table}')"))
                ->pluck('name');

            if (! $indexes->contains($index)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $table) use ($index) {
            $table->dropUnique($index);
        });
    }
};
