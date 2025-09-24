<?php

namespace App\Dto;

class BalanceQueryDto
{
    public function __construct(
        public readonly ?string $accountName = null,
        public readonly bool $includeAvailableCredit = false,
        public readonly bool $includeRecentActivity = false
    ) {}

    public function isValid(): bool
    {
        return !empty($this->accountName);
    }

    public function getMissingFields(): array
    {
        $missing = [];
        
        if (empty($this->accountName)) {
            $missing[] = 'nombre de cuenta';
        }
        
        return $missing;
    }

    public function toArray(): array
    {
        return [
            'account_name' => $this->accountName,
            'include_available_credit' => $this->includeAvailableCredit,
            'include_recent_activity' => $this->includeRecentActivity,
        ];
    }
}