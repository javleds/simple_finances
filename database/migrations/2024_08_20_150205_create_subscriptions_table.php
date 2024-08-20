<?php

use App\Enums\Frequency;
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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount');
            $table->enum('frequency_type', Frequency::values());
            $table->integer('frequency_unit');
            $table->date('started_at');
            $table->date('finished_at')->nullable();
            $table->string('add_frequency')->virtualAs("CONCAT_WS(' ', '+', frequency_unit, frequency_type)");
            $table->string('sub_frequency')->virtualAs("CONCAT_WS(' ', '-', frequency_unit, frequency_type)");
            $table->foreignId('user_id');
            $table->foreignId('feed_account_id')->nullable();
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
        Schema::dropIfExists('subscriptions');
    }
};
