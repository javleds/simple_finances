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
        Schema::create('partial_fixed_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_income_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partial_fixed_incomes');
    }
};
