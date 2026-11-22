<?php

declare(strict_types=1);

namespace App\Dto;

class PersonalMoneySettingsDto
{
    public function __construct(
        public readonly int $accountId,
        public readonly ?string $startedAt,
        public readonly string $openingBalance,
        public readonly bool $enabled,
        public readonly ?string $startMode = null,
        public readonly ?string $operationKey = null,
    ) {
    }
}
