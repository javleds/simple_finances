<?php

use App\Enums\InviteStatus;
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
        Schema::create('account_invites', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->enum('status', InviteStatus::values())->default(InviteStatus::Pending);
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
        Schema::dropIfExists('account_invites');
    }
};
