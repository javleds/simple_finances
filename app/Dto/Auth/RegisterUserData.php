<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Dto\Legal\LegalAcceptanceData;

readonly class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phoneNumber,
        public LegalAcceptanceData $legalAcceptance,
    ) {
    }
}
