<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personal_money_settings', static function (Blueprint $table): void {
            $table->dateTime('activated_at')->nullable();
            $table->unsignedBigInteger('transaction_cutoff_id')->nullable();
            $table->unsignedBigInteger('ledger_cutoff_id')->nullable();
        });
        Schema::create('personal_money_baseline_operations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_key');
            $table->string('payload_hash', 64);
            $table->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_money_baseline_operations');
        Schema::table('personal_money_settings', static function (Blueprint $table): void {
            $table->dropColumn(['activated_at', 'transaction_cutoff_id', 'ledger_cutoff_id']);
        });
    }
};
