<?php

namespace App\Services\Transaction;

use App\Dto\UserPaymentDto;
use App\Enums\TransactionPaymentSource;
use App\Models\Account;

class ResolveOutcomeUserPayments
{
    /**
     * @param array<int, UserPaymentDto> $requestedUserPayments
     * @return array<int, UserPaymentDto>
     */
    public function execute(
        Account $account,
        float $amount,
        int $paidByUserId,
        TransactionPaymentSource $paymentSource,
        array $requestedUserPayments,
    ): array {
        if ($requestedUserPayments !== []) {
            return $requestedUserPayments;
        }

        return $this->defaultAccountPayments($account);
    }

    /**
     * @return array<int, UserPaymentDto>
     */
    private function defaultAccountPayments(Account $account): array
    {
        $users = $account->users()->orderBy('users.id')->get();

        if ($users->isEmpty()) {
            return [
                new UserPaymentDto((int) $account->user_id, 100.0),
            ];
        }

        return $users
            ->map(fn ($user): UserPaymentDto => new UserPaymentDto(
                userId: (int) $user->id,
                percentage: (float) ($user->pivot->percentage ?? 0.0),
            ))
            ->all();
    }
}
