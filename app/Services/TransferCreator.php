<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Models\Account;

class TransferCreator
{
    public function handle(Account $origin, Account $destination, array $data): void
    {
        $amount = $data['amount'];
        $concept = $data['concept'] ?? sprintf('Transferencia de %s a %s', $origin->name, $destination->name);
        $date = $data['scheduled_at'];

        $transactions = collect();

        $transactions->add(
            $destination->transactions()->create([
                'concept' => $concept,
                'amount' => $amount,
                'type' => TransactionType::Income,
                'status' => TransactionStatus::Completed,
                'scheduled_at' => $date,
            ])
        );

        $transactions->add(
            $origin->transactions()->create([
                'concept' => $concept,
                'amount' => $amount,
                'type' => TransactionType::Outcome,
                'status' => TransactionStatus::Completed,
                'scheduled_at' => $date,
            ])
        );

        event(new BulkTransactionSaved($transactions));
    }
}
