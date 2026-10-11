<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_money_settings', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('started_at');
            $table->decimal('opening_balance', 15, 2);
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('personal_money_reimbursements', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ledger_entry_key')->unique();
            $table->decimal('cash_effect', 15, 2);
            $table->string('description');
            $table->string('account_name');
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('personal_money_links', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reimbursement_id')->nullable()->constrained('personal_money_reimbursements')->cascadeOnDelete();
            $table->string('resolution', 16);
            $table->string('source_signature', 64);
            $table->unsignedBigInteger('source_account_id');
            $table->string('account_name');
            $table->string('concept');
            $table->dateTime('occurred_at');
            $table->decimal('cash_effect', 15, 2);
            $table->string('kind', 32);
            $table->timestamps();
            $table->unique(['user_id', 'transaction_id']);
            $table->unique(['user_id', 'reimbursement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_money_links');
        Schema::dropIfExists('personal_money_reimbursements');
        Schema::dropIfExists('personal_money_settings');
    }
};
