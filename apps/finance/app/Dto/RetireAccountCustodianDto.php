<?php

declare(strict_types=1);

namespace App\Dto;

use function array_map;
use function round;

final readonly class RetireAccountCustodianDto
{
    public function __construct(public array $distributions)
    {
    }

    public static function fromArray(array $payload): self
    {
        return new self(array_map(static fn (array $distribution): array => [
            'user_id'      => (int) $distribution['user_id'],
            'amount_cents' => (int) round((float) $distribution['amount'] * 100),
        ], $payload['distributions'] ?? []));
    }
}
