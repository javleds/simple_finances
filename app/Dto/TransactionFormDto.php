<?php

namespace App\Dto;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;

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
        public string $scheduledAt,
        public ?int $finanialGoalId,
    ) {}

    public static function fromFormArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            type: $data['type'],
            status: $data['status'] ?? TransactionStatus::Completed,
            concept: $data['concept'],
            amount: $data['amount'],
            accountId: $data['account_id'],
            splitBetweenUsers: $data['split_between_users'] ?? false,
            userPayments: collect($data['user_payments'])->map(fn (array $userPayment) => UserPaymentDto::fromFormArray($userPayment))->toArray() ?? [],
            scheduledAt: $data['scheduled_at'] ?? '',
            finanialGoalId: $data['financial_goal_id'] ?? null,
        );
    }
}
