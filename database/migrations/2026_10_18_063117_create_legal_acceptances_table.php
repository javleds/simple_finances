<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('legal_acceptances', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version');
            $table->string('privacy_version');
            $table->string('terms_hash', 64);
            $table->string('privacy_hash', 64);
            $table->json('snapshot');
            $table->boolean('terms_accepted');
            $table->boolean('privacy_policy_accepted');
            $table->boolean('financial_data_consent');
            $table->boolean('is_adult');
            $table->timestamp('accepted_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'terms_hash', 'privacy_hash'], 'legal_acceptances_user_documents_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
