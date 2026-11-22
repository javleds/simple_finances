<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('account_member_ledger_entries', static function (Blueprint $table): void {
            $table->string('type', 64)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('account_member_ledger_entries')->whereRaw('LENGTH(type) > 32')->exists()) {
            throw new RuntimeException('Existing ledger types cannot be truncated.');
        }
        Schema::table('account_member_ledger_entries', static function (Blueprint $table): void {
            $table->string('type', 32)->change();
        });
    }
};
