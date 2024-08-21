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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 10);
            $table->date('done_at');
            $table->date('started_at');
            $table->date('next_payment_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->unsignedInteger('number_of_payments')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('payments_done')->nullable();
            $table->decimal('paid', 10)->nullable();
            $table->decimal('to_pay', 10)->nullable();
            $table->foreignId('feed_account_id')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('feed_account_id')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
