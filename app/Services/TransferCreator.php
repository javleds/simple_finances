<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Models\Account;
use Filament\Notifications\Notification;

class TransferCreator
{
    public function handle(Account $origin, Account $destination, array $data): void
    {
        $amount = $data['amount'];
        $concept = $data['concept'] ?? sprintf('Transferencia de %s a %s', $origin->name, $destination->name);
        $date = $data['scheduled_at'];

        $transactions = collect();

        $transactions->add(
            $origin->transactions()->create([
                'concept' => $concept,
                'amount' => $amount,
                'type' => TransactionType::Outcome,
                'scheduled_at' => $date,
            ])
        );

        $transactions->add(
            $destination->transactions()->create([
                'concept' => $concept,
                'amount' => $amount,
                'type' => TransactionType::Income,
                'scheduled_at' => $date,
            ])
        );

        event(new BulkTransactionSaved($transactions));

        Notification::make()
            ->success()
            ->title('Transacción realizada')
            ->send();
    }
}
