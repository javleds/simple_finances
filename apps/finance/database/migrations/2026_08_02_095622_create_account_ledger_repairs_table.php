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
        Schema::create('account_ledger_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('reverses_repair_id')->nullable()->constrained('account_ledger_repairs')->nullOnDelete();
            $table->foreignId('reversed_by_repair_id')->nullable()->constrained('account_ledger_repairs')->nullOnDelete();
            $table->string('status', 24)->default('applied');
            $table->string('issue_code', 80);
            $table->string('repair_type', 80);
            $table->string('confidence', 24)->default('high');
            $table->json('input_payload')->nullable();
            $table->json('evidence_payload')->nullable();
            $table->json('preview_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'issue_code']);
            $table->index(['account_id', 'target_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_ledger_repairs');
    }
};
