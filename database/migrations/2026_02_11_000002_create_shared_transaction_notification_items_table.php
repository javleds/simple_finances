<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_transaction_notification_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('modifier_id');
            $table->string('action');
            $table->string('concept');
            $table->string('type');
            $table->float('amount');
            $table->timestamp('scheduled_at');
            $table->timestamps();

            $table->index(['batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_transaction_notification_items');
    }
};
