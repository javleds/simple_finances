<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;

class AccountSummaryCreator
{
    public function handle(User $user, Account $account): string
    {
        $transactions = $account->transactions()
            ->withoutGlobalScopes()
            ->orderBy('scheduled_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $csvData = $transactions->map(function ($transaction) {
            return $transaction->toArray();
        })->toArray();

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'));
        }

        $csvFilePath = storage_path(sprintf('app/temp/user_%s_account_%s_transactions.csv', $user->id, $account->id));
        $handle = fopen($csvFilePath, 'w');

        fputcsv($handle, array_keys($csvData[0]));

        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $csvFilePath;
    }
}
