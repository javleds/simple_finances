<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', static function (Blueprint $table): void {
            $table->string('transaction_type', 7)->default('both');
        });

        foreach (['income', 'outcome'] as $type) {
            DB::table('categories')
                ->whereIn('id', DB::table('transactions')->select('category_id')->where('type', $type)->whereNotNull('category_id'))
                ->whereNotIn('id', DB::table('transactions')->select('category_id')->where('type', '!=', $type)->whereNotNull('category_id'))
                ->update(['transaction_type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', static function (Blueprint $table): void {
            $table->dropColumn('transaction_type');
        });
    }
};
