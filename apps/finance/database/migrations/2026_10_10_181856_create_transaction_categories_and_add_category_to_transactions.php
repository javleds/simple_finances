<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('categories', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('name', 100);
            $table->string('normalized_name', 100);
            $table->timestamps();
            $table->unique(['user_id', 'normalized_name']);
            $table->unique(['account_id', 'normalized_name']);
        });
        Schema::table('accounts', static function (Blueprint $table): void {
            $table->boolean('uses_shared_categories')->default(false);
        });
        Schema::table('transactions', static function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
        });
        DB::table('accounts')->whereIn('id', DB::table('account_user')->select('account_id')->groupBy('account_id')->havingRaw('COUNT(*) > 1'))->update(['uses_shared_categories' => true]);
    }

    public function down(): void
    {
        Schema::table('transactions', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::table('accounts', static function (Blueprint $table): void {
            $table->dropColumn('uses_shared_categories');
        });
        Schema::dropIfExists('categories');
    }
};
