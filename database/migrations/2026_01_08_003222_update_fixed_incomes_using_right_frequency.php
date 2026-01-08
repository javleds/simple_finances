<?php

use App\Enums\FixedIncomeFrequency;
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
        Schema::table('fixed_incomes', function (Blueprint $table) {
            $table->dropColumn('frequency');
            $table->enum('frequency', FixedIncomeFrequency::values())->default(FixedIncomeFrequency::SemiMonthly->value);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_incomes', function (Blueprint $table) {
            //
        });
    }
};
