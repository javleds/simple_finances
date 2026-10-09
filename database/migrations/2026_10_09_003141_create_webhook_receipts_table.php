<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->char('payload_hash', 64);
            $table->longText('payload');
            $table->string('status', 16)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
    }
};
