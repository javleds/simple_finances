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
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('credit_card')->default(false);
            $table->decimal('credit_line', 10)->nullable();
            $table->integer('cutoff_day')->nullable();
            $table->dateTime('next_cutoff_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('credit_card');
            $table->dropColumn('credit_line');
            $table->dropColumn('cutoff_day');
            $table->dropColumn('next_cutoff_date');
        });
    }
};
