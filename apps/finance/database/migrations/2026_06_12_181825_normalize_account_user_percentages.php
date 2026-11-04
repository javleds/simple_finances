<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('account_user')
            ->select('account_id')
            ->distinct()
            ->orderBy('account_id')
            ->chunk(100, function (Collection $accounts): void {
                foreach ($accounts as $account) {
                    $this->normalizeAccountPercentages((int) $account->account_id);
                }
            });
    }

    public function down(): void
    {
        return;
    }

    private function normalizeAccountPercentages(int $accountId): void
    {
        $users = DB::table('account_user')
            ->where('account_id', $accountId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'percentage']);

        if ($users->isEmpty()) {
            return;
        }

        $currentTotal = round($users->sum(fn (object $user): float => max(0.0, (float) $user->percentage)), 2);

        if ($currentTotal <= 0.0) {
            $this->assignFirstUserAsFullOwner($users);

            return;
        }

        if ($currentTotal === 100.0) {
            return;
        }

        $this->normalizeUsers($users, $currentTotal);
    }

    private function assignFirstUserAsFullOwner(Collection $users): void
    {
        $firstUser = $users->first();
        $now = now();

        foreach ($users as $user) {
            DB::table('account_user')
                ->where('id', $user->id)
                ->update([
                    'percentage' => $user->id === $firstUser->id ? 100.0 : 0.0,
                    'updated_at' => $now,
                ]);
        }
    }

    private function normalizeUsers(Collection $users, float $currentTotal): void
    {
        $lastIndex = $users->count() - 1;
        $allocatedPercentage = 0.0;
        $now = now();

        foreach ($users->values() as $index => $user) {
            $percentage = $index === $lastIndex
                ? round(100.0 - $allocatedPercentage, 2)
                : round((max(0.0, (float) $user->percentage) / $currentTotal) * 100.0, 2);

            $allocatedPercentage += $percentage;

            DB::table('account_user')
                ->where('id', $user->id)
                ->update([
                    'percentage' => $percentage,
                    'updated_at' => $now,
                ]);
        }
    }
};
