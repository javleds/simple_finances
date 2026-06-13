<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->mergeDuplicateAccountUsers();

        Schema::table('account_user', function (Blueprint $table) {
            $table->unique(['account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('account_user', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'user_id']);
        });
    }

    private function mergeDuplicateAccountUsers(): void
    {
        DB::table('account_user')
            ->select('account_id', 'user_id')
            ->groupBy('account_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('account_id')
            ->orderBy('user_id')
            ->chunk(100, function (Collection $duplicates): void {
                foreach ($duplicates as $duplicate) {
                    $this->mergeDuplicateAccountUser(
                        (int) $duplicate->account_id,
                        (int) $duplicate->user_id,
                    );
                }
            });
    }

    private function mergeDuplicateAccountUser(int $accountId, int $userId): void
    {
        $rows = DB::table('account_user')
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'percentage']);

        if ($rows->count() <= 1) {
            return;
        }

        $primaryRow = $rows->first();
        $duplicateIds = $rows
            ->skip(1)
            ->pluck('id')
            ->all();

        DB::table('account_user')
            ->where('id', $primaryRow->id)
            ->update([
                'percentage' => round($rows->sum(fn (object $row): float => (float) $row->percentage), 2),
                'updated_at' => now(),
            ]);

        DB::table('account_user')
            ->whereIn('id', $duplicateIds)
            ->delete();
    }
};
