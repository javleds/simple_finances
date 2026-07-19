<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('paid_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('custodian_user_id')
                ->nullable()
                ->after('paid_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('payment_source', 32)
                ->nullable()
                ->after('custodian_user_id');
            $table->timestamp('legacy_migrated_at')->nullable()->after('payment_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['paid_by_user_id']);
            $table->dropForeign(['custodian_user_id']);
            $table->dropColumn([
                'paid_by_user_id',
                'custodian_user_id',
                'payment_source',
                'legacy_migrated_at',
            ]);
        });
    }
};
