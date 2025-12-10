<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

class AccountSummaryCreator
{
    public function handle(User $user, Account $account): ?string
    {
        $transactions = $account->transactions()
            ->withoutGlobalScopes()
            ->completed()
            ->with('user')
            ->orderBy('scheduled_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $csvData = $transactions->map(function (Transaction $transaction) {
            return [
                'Concepto' => $transaction->concept,
                'Monto' => as_money($transaction->amount),
                'Tipo' => $transaction->type->getLabel(),
                'Usuario' => $transaction->user->name,
                'Fecha' => $transaction->scheduled_at->format('d-m-Y'),
            ];
        })->toArray();

        if (count($csvData) === 0) {
            return null;
        }

        if (! file_exists(storage_path('app/temp'))) {
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
