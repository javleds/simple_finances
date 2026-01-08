<?php

use App\Enums\FixedOutcomeType;
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
        Schema::create('fixed_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_income_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->enum('type', FixedOutcomeType::values())->default(FixedOutcomeType::Savings->value);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_outcomes');
    }
};
