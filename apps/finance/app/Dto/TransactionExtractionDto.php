<?php

declare(strict_types=1);

namespace App\Dto;

use function in_array;
use function trim;

class TransactionExtractionDto
{
    public function __construct(
        public readonly ?string $account = null,
        public readonly ?string $amount = null,
        public readonly ?string $type = null,
        public readonly ?string $concept = null,
        public readonly ?string $date = null,
        public readonly ?string $financialGoal = null,
    ) {
    }

    public function isValid(): bool
    {
        return null !== $this->account
            && null !== $this->amount
            && null !== $this->type
            && null !== $this->concept
            && in_array($this->type, ['income', 'outcome'])
            && '' !== trim($this->concept);
    }

    public function getMissingFields(): array
    {
        $missing = [];

        if (null === $this->account) {
            $missing[] = 'account';
        }

        if (null === $this->amount) {
            $missing[] = 'amount';
        }

        if (null === $this->type || ! in_array($this->type, ['income', 'outcome'])) {
            $missing[] = 'type';
        }

        if (null === $this->concept || '' === trim($this->concept)) {
            $missing[] = 'concept';
        }

        return $missing;
    }

    public function toArray(): array
    {
        return [
            'account'        => $this->account,
            'amount'         => $this->amount,
            'type'           => $this->type,
            'concept'        => $this->concept,
            'date'           => $this->date,
            'financial_goal' => $this->financialGoal,
        ];
    }
}
