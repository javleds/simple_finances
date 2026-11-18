<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('virtual_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->timestamp('virtual_retired_at')->nullable();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->boolean('virtual_custodian_opening')->default(false);
            $table->boolean('uses_virtual_custodian_rules')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('virtual_account_id')->exists()) {
            throw new RuntimeException('Cannot remove virtual custodian identity fields while custodians exist.');
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['virtual_custodian_opening', 'uses_virtual_custodian_rules']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('virtual_account_id');
            $table->dropColumn('virtual_retired_at');
        });
    }
};
