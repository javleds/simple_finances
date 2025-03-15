<?php

use App\Models\Account;
use App\Models\User;
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
        Schema::create('account_user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on(app(User::class)->getTable())->cascadeOnDelete();
            $table->foreignId('account_id')->references('id')->on(app(Account::class)->getTable())->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_user_notifications');
    }
};
