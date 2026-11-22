<?php

declare(strict_types=1);

namespace App\Dto;

use function array_map;

use FinSi\FinanceDomain\Money\Decimal;

final readonly class RetireAccountCustodianDto
{
    public function __construct(public array $distributions)
    {
    }

    public static function fromArray(array $payload): self
    {
        return new self(array_map(static fn (array $distribution): array => [
            'user_id'      => (int) $distribution['user_id'],
            'amount_cents' => Decimal::minor($distribution['amount']),
        ], $payload['distributions'] ?? []));
    }
}
