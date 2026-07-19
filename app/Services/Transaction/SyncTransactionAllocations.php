<?php

namespace App\Services\Transaction;

use App\Dto\UserPaymentDto;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class SyncTransactionAllocations
{
    public function __construct(
        private readonly BuildSplitTransactionAllocations $buildSplitTransactionAllocations,
    ) {}

    public function execute(Transaction $transaction, array $userPayments): void
    {
        $transaction->allocations()->delete();

        if ($transaction->type !== TransactionType::Outcome || $userPayments === []) {
            return;
        }

        foreach ($this->buildSplitTransactionAllocations->execute($transaction->amount, $userPayments) as $allocation) {
            $transaction->allocations()->create([
                'user_id' => $allocation->userId,
                'percentage' => $allocation->percentage,
                'amount' => $allocation->amount,
            ]);
        }
    }

    public function defaultOutcomeAllocation(Transaction $transaction): array
    {
        return [
            new UserPaymentDto(
                userId: $transaction->user_id,
                percentage: 100.0,
            ),
        ];
    }
}
