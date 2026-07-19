<?php

namespace App\Dto;

use App\Enums\TransactionStatus;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionType;
use Carbon\CarbonInterface;

class TransactionFormDto
{
    public function __construct(
        public ?int $id,
        public TransactionType $type,
        public TransactionStatus $status,
        public string $concept,
        public float $amount,
        public int $accountId,
        public ?int $paidByUserId,
        public ?int $custodianUserId,
        public TransactionPaymentSource $paymentSource,
        public bool $splitBetweenUsers,
        public array $userPayments,
        public string|CarbonInterface $scheduledAt,
        public ?int $financialGoalId,
    ) {}

    public static function fromFormArray(array $data): self
    {
        $type = $data['type'] ?? TransactionType::Outcome->value;
        $status = $data['status'] ?? TransactionStatus::Completed->value;

        return new self(
            id: $data['id'] ?? null,
            type: $type instanceof TransactionType ? $type : TransactionType::from($type),
            status: $status instanceof TransactionStatus ? $status : TransactionStatus::from($status),
            concept: $data['concept'],
            amount: (float) $data['amount'],
            accountId: (int) $data['account_id'],
            paidByUserId: isset($data['paid_by_user_id']) ? (int) $data['paid_by_user_id'] : null,
            custodianUserId: isset($data['custodian_user_id']) ? (int) $data['custodian_user_id'] : null,
            paymentSource: self::paymentSourceFromFormArray($data),
            splitBetweenUsers: $data['split_between_users'] ?? false,
            userPayments: collect($data['user_payments'] ?? [])->map(fn (array $userPayment) => UserPaymentDto::fromFormArray($userPayment))->all(),
            scheduledAt: $data['scheduled_at'] ?? '',
            financialGoalId: $data['financial_goal_id'] ?? null,
        );
    }

    private static function paymentSourceFromFormArray(array $data): TransactionPaymentSource
    {
        $source = $data['payment_source'] ?? TransactionPaymentSource::AccountFund->value;

        if ($source instanceof TransactionPaymentSource) {
            return $source;
        }

        return TransactionPaymentSource::from($source);
    }
}
