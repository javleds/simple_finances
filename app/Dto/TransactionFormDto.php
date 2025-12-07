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
        public int $finanialGoalId,
    ) {}

    public static function fromFormArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            type: TransactionType::from($data['type']),
            status: TransactionStatus::from($data['status']),
            concept: $data['concept'],
            amount: $data['amount'],
            accountId: $data['account_id'],
            splitBetweenUsers: $data['split_between_users'] ?? false,
            userPayments: $data['user_payments'] ?? [],
            scheduledAt: $data['scheduled_at'] ?? '',
            finanialGoalId: $data['financial_goal_id'] ?? 0,
        );
    }
}
