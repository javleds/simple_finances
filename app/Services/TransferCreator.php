<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Services\Accounts\RecalculateAccountBalance;

class TransferCreator
{
    public function __construct(private readonly RecalculateAccountBalance $recalculateAccountBalance) {}

    public function handle(Account $origin, Account $destination, array $data): void
    {
        $amount = $data['amount'];
        $concept = $data['concept'] ?? sprintf('Transferencia de %s a %s', $origin->name, $destination->name);
        $date = $data['scheduled_at'];

        $destination->transactions()->create([
            'concept' => $concept,
            'amount' => $amount,
            'type' => TransactionType::Income,
            'status' => TransactionStatus::Completed,
            'scheduled_at' => $date,
        ]);

        $origin->transactions()->create([
            'concept' => $concept,
            'amount' => $amount,
            'type' => TransactionType::Outcome,
            'status' => TransactionStatus::Completed,
            'scheduled_at' => $date,
        ]);

        $this->recalculateAccountBalance->execute($destination);
        $this->recalculateAccountBalance->execute($origin);
    }
}
