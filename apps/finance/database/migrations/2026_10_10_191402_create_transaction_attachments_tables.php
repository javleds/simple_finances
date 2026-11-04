<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('transaction_attachment_capacity', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('reserved_bytes')->default(0);
        });
        DB::table('transaction_attachment_capacity')->insert(['id' => 1, 'reserved_bytes' => 0]);
        Schema::create('transaction_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('upload_id');
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime_type', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('source_path')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('reserved_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'upload_id']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_attachments');
        Schema::dropIfExists('transaction_attachment_capacity');
    }
};
