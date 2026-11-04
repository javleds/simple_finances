<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_verification_locks', function (Blueprint $table): void {
            $table->string('phone_number', 13)->primary();
        });

        Schema::create('whatsapp_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number', 13);
            $table->string('code_hash');
            $table->string('status', 20)->default('sending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['phone_number', 'created_at']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_verification_codes');
        Schema::dropIfExists('whatsapp_verification_locks');
    }
};
