<?php

namespace App\Dto;

use App\Enums\TransactionStatus;
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
        public bool $splitBetweenUsers,
        public array $userPayments,
        public string|CarbonInterface $scheduledAt,
        public ?int $finanialGoalId,
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
            splitBetweenUsers: $data['split_between_users'] ?? false,
            userPayments: collect($data['user_payments'] ?? [])->map(fn (array $userPayment) => UserPaymentDto::fromFormArray($userPayment))->all(),
            scheduledAt: $data['scheduled_at'] ?? '',
            finanialGoalId: $data['financial_goal_id'] ?? null,
        );
    }
}
