<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Dto\UserPaymentDto;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Money\Decimal;

class ResolveOutcomeUserPayments
{
    /**
     * @param array<int, UserPaymentDto> $requestedUserPayments
     *
     * @return array<int, UserPaymentDto>
     */
    public function execute(
        Account $account,
        string $amount,
        int $paidByUserId,
        TransactionPaymentSource $paymentSource,
        array $requestedUserPayments,
    ): array {
        if ([] !== $requestedUserPayments) {
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
                new UserPaymentDto((int) $account->user_id, '100.00'),
            ];
        }

        return $users
            ->map(fn ($user): UserPaymentDto => new UserPaymentDto(
                userId: (int) $user->id,
                percentage: Decimal::normalize($user->pivot->percentage ?? '0.00'),
            ))
            ->all();
    }
}
