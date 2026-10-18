<?php

declare(strict_types=1);

namespace App\Dto\Legal;

readonly class LegalAcceptanceData
{
    public function __construct(
        public string $termsHash,
        public string $privacyHash,
        public bool $termsAccepted,
        public bool $privacyPolicyAccepted,
        public bool $isAdult,
    ) {
    }
}
