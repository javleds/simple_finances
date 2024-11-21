<?php

use App\Enums\FinancialGoalStatus;
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
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 10);
            $table->integer('progress')->default(0);
            $table->date('must_completed_at')->nullable();
            $table->enum('status', FinancialGoalStatus::values())->default(FinancialGoalStatus::InProgress);
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_goals');
    }
};
