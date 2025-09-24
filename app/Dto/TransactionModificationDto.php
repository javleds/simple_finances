<?php

namespace App\Dto;

class TransactionModificationDto
{
    public function __construct(
        public readonly ?string $newConcept = null,
        public readonly ?float $newAmount = null,
        public readonly ?string $newType = null,
        public readonly ?string $newDate = null,
        public readonly ?string $newAccountName = null,
        public readonly ?string $newFinancialGoal = null,
        public readonly bool $confirmModification = false
    ) {}

    public function hasChanges(): bool
    {
        return !is_null($this->newConcept) ||
               !is_null($this->newAmount) ||
               !is_null($this->newType) ||
               !is_null($this->newDate) ||
               !is_null($this->newAccountName) ||
               !is_null($this->newFinancialGoal);
    }

    public function getChangedFields(): array
    {
        $changed = [];
        
        if (!is_null($this->newConcept)) {
            $changed['concept'] = $this->newConcept;
        }
        
        if (!is_null($this->newAmount)) {
            $changed['amount'] = $this->newAmount;
        }
        
        if (!is_null($this->newType)) {
            $changed['type'] = $this->newType;
        }
        
        if (!is_null($this->newDate)) {
            $changed['date'] = $this->newDate;
        }
        
        if (!is_null($this->newAccountName)) {
            $changed['account_name'] = $this->newAccountName;
        }
        
        if (!is_null($this->newFinancialGoal)) {
            $changed['financial_goal'] = $this->newFinancialGoal;
        }
        
        return $changed;
    }

    public function toArray(): array
    {
        return [
            'new_concept' => $this->newConcept,
            'new_amount' => $this->newAmount,
            'new_type' => $this->newType,
            'new_date' => $this->newDate,
            'new_account_name' => $this->newAccountName,
            'new_financial_goal' => $this->newFinancialGoal,
            'confirm_modification' => $this->confirmModification,
        ];
    }
}