<?php

namespace App\Dto;

class RecentTransactionsQueryDto
{
    public function __construct(
        public readonly ?string $accountName = null,
        public readonly int $limit = 5,
        public readonly bool $includeUserNames = true,
        public readonly bool $includeFormattedDates = true
    ) {}

    public function isValid(): bool
    {
        return ! empty($this->accountName) && $this->limit > 0;
    }

    public function getMissingFields(): array
    {
        $missing = [];

        if (empty($this->accountName)) {
            $missing[] = 'nombre de cuenta';
        }

        if ($this->limit <= 0) {
            $missing[] = 'límite válido de transacciones';
        }

        return $missing;
    }

    public function toArray(): array
    {
        return [
            'account_name' => $this->accountName,
            'limit' => $this->limit,
            'include_user_names' => $this->includeUserNames,
            'include_formatted_dates' => $this->includeFormattedDates,
        ];
    }
}
