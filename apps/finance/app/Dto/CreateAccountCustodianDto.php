<?php

declare(strict_types=1);

namespace App\Dto;

use function array_map;

use FinSi\FinanceDomain\Money\Decimal;

use function trim;

final readonly class CreateAccountCustodianDto
{
    public function __construct(
        public string $name,
        public int $openingBalanceCents,
        public array $transfers,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            name: trim($payload['name']),
            openingBalanceCents: Decimal::minor($payload['opening_balance'] ?? '0.00'),
            transfers: array_map(static fn (array $transfer): array => [
                'user_id'      => (int) $transfer['user_id'],
                'amount_cents' => Decimal::minor($transfer['amount']),
            ], $payload['transfers'] ?? []),
        );
    }
}
