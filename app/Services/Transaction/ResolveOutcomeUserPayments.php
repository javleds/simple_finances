<?php

namespace App\Services\Transaction;

use App\Dto\UserPaymentDto;
use App\Enums\TransactionPaymentSource;
use App\Models\Account;
use App\Services\Accounts\BuildAccountCustodySummary;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResolveOutcomeUserPayments
{
    public function __construct(
        private readonly BuildAccountCustodySummary $buildAccountCustodySummary,
    ) {}

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
        $positiveCustodians = $this->buildAccountCustodySummary->positiveCustodians($account);
        $positiveCustodyTotal = round((float) $positiveCustodians->sum('amount'), 2);

        if ($paymentSource === TransactionPaymentSource::AccountFund && round((float) $account->balance, 2) <= 0.0) {
            throw ValidationException::withMessages([
                'payment_source' => 'No puedes pagar con fondos de la cuenta cuando no hay fondos disponibles.',
            ]);
        }

        if ($paymentSource === TransactionPaymentSource::AccountFund && $positiveCustodyTotal > 0.0) {
            return $this->paymentsFromAmounts(
                totalAmount: $amount,
                amountsByUser: $this->custodianAmounts($positiveCustodians, min($amount, $positiveCustodyTotal)),
            );
        }

        if ($paymentSource === TransactionPaymentSource::MemberOutOfPocket && $positiveCustodyTotal > 0.0) {
            return $this->paymentsForOutOfPocketExpenseWithCustody(
                amount: $amount,
                paidByUserId: $paidByUserId,
                positiveCustodians: $positiveCustodians,
                positiveCustodyTotal: $positiveCustodyTotal,
                requestedUserPayments: $requestedUserPayments,
                defaultUserPayments: $this->defaultAccountPayments($account),
            );
        }

        if ($requestedUserPayments !== []) {
            return $requestedUserPayments;
        }

        return $this->defaultAccountPayments($account);
    }

    /**
     * @param iterable<int, array{user_id: int, amount: float}> $positiveCustodians
     * @return array<int, UserPaymentDto>
     */
    private function paymentsForOutOfPocketExpenseWithCustody(
        float $amount,
        int $paidByUserId,
        Collection $positiveCustodians,
        float $positiveCustodyTotal,
        array $requestedUserPayments,
        array $defaultUserPayments,
    ): array {
        if ($this->userHasPositiveCustody($positiveCustodians, $paidByUserId)) {
            return $requestedUserPayments !== [] ? $requestedUserPayments : $defaultUserPayments;
        }

        $coveredByCustody = min($amount, $positiveCustodyTotal);
        $excess = round($amount - $coveredByCustody, 2);
        $amountsByUser = $this->custodianAmounts($positiveCustodians, $coveredByCustody);

        if ($excess > 0.0) {
            $excessPayments = $requestedUserPayments !== [] ? $requestedUserPayments : $defaultUserPayments;
            $amountsByUser = $this->mergeAmounts($amountsByUser, $this->amountsFromPercentages($excess, $excessPayments));
        }

        return $this->paymentsFromAmounts($amount, $amountsByUser);
    }

    /**
     * @param Collection<int, array{user_id: int, amount: float}> $positiveCustodians
     * @return array<int, float>
     */
    private function custodianAmounts(Collection $positiveCustodians, float $targetAmount): array
    {
        if ($targetAmount <= 0.0) {
            return [];
        }

        $total = round((float) $positiveCustodians->sum('amount'), 2);
        $allocatedAmount = 0.0;
        $lastIndex = $positiveCustodians->count() - 1;

        return $positiveCustodians
            ->mapWithKeys(function (array $custodian, int $index) use ($targetAmount, $total, &$allocatedAmount, $lastIndex): array {
                $amount = $index === $lastIndex
                    ? round($targetAmount - $allocatedAmount, 2)
                    : round($targetAmount * ((float) $custodian['amount'] / $total), 2);

                $allocatedAmount = round($allocatedAmount + $amount, 2);

                return [(int) $custodian['user_id'] => $amount];
            })
            ->all();
    }

    /**
     * @param array<int, UserPaymentDto> $payments
     * @return array<int, float>
     */
    private function amountsFromPercentages(float $amount, array $payments): array
    {
        $positivePayments = array_values(array_filter(
            $payments,
            fn (UserPaymentDto $payment): bool => $payment->percentage > 0
        ));

        $lastIndex = count($positivePayments) - 1;
        $allocatedAmount = 0.0;
        $amounts = [];

        foreach ($positivePayments as $index => $payment) {
            $paymentAmount = $index === $lastIndex
                ? round($amount - $allocatedAmount, 2)
                : round($amount * ($payment->percentage / 100), 2);

            $allocatedAmount = round($allocatedAmount + $paymentAmount, 2);
            $amounts[$payment->userId] = round(($amounts[$payment->userId] ?? 0.0) + $paymentAmount, 2);
        }

        return $amounts;
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     * @return array<int, float>
     */
    private function mergeAmounts(array $left, array $right): array
    {
        foreach ($right as $userId => $amount) {
            $left[$userId] = round(($left[$userId] ?? 0.0) + $amount, 2);
        }

        return $left;
    }

    /**
     * @param array<int, float> $amountsByUser
     * @return array<int, UserPaymentDto>
     */
    private function paymentsFromAmounts(float $totalAmount, array $amountsByUser): array
    {
        $positiveAmounts = array_filter($amountsByUser, fn (float $amount): bool => $amount > 0.0);
        $lastUserId = array_key_last($positiveAmounts);
        $allocatedPercentage = 0.0;
        $payments = [];

        foreach ($positiveAmounts as $userId => $amount) {
            $percentage = $userId === $lastUserId
                ? round(100.0 - $allocatedPercentage, 2)
                : round(($amount / $totalAmount) * 100, 2);

            $allocatedPercentage = round($allocatedPercentage + $percentage, 2);
            $payments[] = new UserPaymentDto((int) $userId, $percentage);
        }

        return $payments;
    }

    /**
     * @param iterable<int, array{user_id: int, amount: float}> $positiveCustodians
     */
    private function userHasPositiveCustody(iterable $positiveCustodians, int $userId): bool
    {
        return collect($positiveCustodians)
            ->contains(fn (array $custodian): bool => (int) $custodian['user_id'] === $userId);
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
