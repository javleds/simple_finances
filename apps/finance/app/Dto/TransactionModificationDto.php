<?php

declare(strict_types=1);

namespace App\Dto;

class TransactionModificationDto
{
    public function __construct(
        public readonly ?string $newConcept = null,
        public readonly ?string $newAmount = null,
        public readonly ?string $newType = null,
        public readonly ?string $newDate = null,
        public readonly ?string $newAccountName = null,
        public readonly ?string $newFinancialGoal = null,
        public readonly bool $confirmModification = false,
    ) {
    }

    public function hasChanges(): bool
    {
        return null !== $this->newConcept
               || null !== $this->newAmount
               || null !== $this->newType
               || null !== $this->newDate
               || null !== $this->newAccountName
               || null !== $this->newFinancialGoal;
    }

    public function getChangedFields(): array
    {
        $changed = [];

        if (null !== $this->newConcept) {
            $changed['concept'] = $this->newConcept;
        }

        if (null !== $this->newAmount) {
            $changed['amount'] = $this->newAmount;
        }

        if (null !== $this->newType) {
            $changed['type'] = $this->newType;
        }

        if (null !== $this->newDate) {
            $changed['date'] = $this->newDate;
        }

        if (null !== $this->newAccountName) {
            $changed['account_name'] = $this->newAccountName;
        }

        if (null !== $this->newFinancialGoal) {
            $changed['financial_goal'] = $this->newFinancialGoal;
        }

        return $changed;
    }

    public function toArray(): array
    {
        return [
            'new_concept'          => $this->newConcept,
            'new_amount'           => $this->newAmount,
            'new_type'             => $this->newType,
            'new_date'             => $this->newDate,
            'new_account_name'     => $this->newAccountName,
            'new_financial_goal'   => $this->newFinancialGoal,
            'confirm_modification' => $this->confirmModification,
        ];
    }
}
