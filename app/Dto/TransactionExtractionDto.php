<?php

namespace App\Dto;

class TransactionExtractionDto
{
    public function __construct(
        public readonly ?string $account = null,
        public readonly ?float $amount = null,
        public readonly ?string $type = null,
        public readonly ?string $concept = null,
        public readonly ?string $date = null,
        public readonly ?string $financialGoal = null,
    ) {}

    public function isValid(): bool
    {
        return ! is_null($this->account)
            && ! is_null($this->amount)
            && ! is_null($this->type)
            && ! is_null($this->concept)
            && in_array($this->type, ['income', 'outcome'])
            && trim($this->concept) !== '';
    }

    public function getMissingFields(): array
    {
        $missing = [];

        if (is_null($this->account)) {
            $missing[] = 'account';
        }

        if (is_null($this->amount)) {
            $missing[] = 'amount';
        }

        if (is_null($this->type) || ! in_array($this->type, ['income', 'outcome'])) {
            $missing[] = 'type';
        }

        if (is_null($this->concept) || trim($this->concept) === '') {
            $missing[] = 'concept';
        }

        return $missing;
    }

    public function toArray(): array
    {
        return [
            'account' => $this->account,
            'amount' => $this->amount,
            'type' => $this->type,
            'concept' => $this->concept,
            'date' => $this->date,
            'financial_goal' => $this->financialGoal,
        ];
    }
}
