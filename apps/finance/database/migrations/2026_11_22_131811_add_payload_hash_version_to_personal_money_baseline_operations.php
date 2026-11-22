<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personal_money_baseline_operations', static function (Blueprint $table): void {
            $table->unsignedTinyInteger('payload_hash_version')->default(1);
        });
    }

    public function down(): void
    {
        if (Illuminate\Support\Facades\DB::table('personal_money_baseline_operations')->where('payload_hash_version', 2)->exists()) {
            throw new RuntimeException('Version 2 idempotency records require the exact-money application.');
        }

        Schema::table('personal_money_baseline_operations', static function (Blueprint $table): void {
            $table->dropColumn('payload_hash_version');
        });
    }
};
