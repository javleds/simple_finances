<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->decimal('achieved_amount', 10, 2)->default(0)->after('amount');
            $table->float('progress')->default(0.0)->change();
        });

        DB::table('financial_goals')
            ->select(['id', 'amount'])
            ->orderBy('id')
            ->chunkById(100, function ($goals): void {
                foreach ($goals as $goal) {
                    $achievedAmount = (float) DB::table('transactions')
                        ->where('financial_goal_id', $goal->id)
                        ->where('type', 'income')
                        ->where('status', 'completed')
                        ->sum('amount');

                    $amount = (float) $goal->amount;
                    $progress = $amount <= 0.0 ? 0.0 : min(($achievedAmount * 100) / $amount, 100.0);

                    DB::table('financial_goals')
                        ->where('id', $goal->id)
                        ->update([
                            'achieved_amount' => $achievedAmount,
                            'progress' => $progress,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->integer('progress')->default(0)->change();
            $table->dropColumn('achieved_amount');
        });
    }
};
