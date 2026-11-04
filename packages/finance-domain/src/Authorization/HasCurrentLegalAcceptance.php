<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Authorization;

use FinSi\FinanceDomain\Models\LegalAcceptance;
use FinSi\FinanceDomain\Queries\UserOwnedResources;

class HasCurrentLegalAcceptance
{
    public function __construct(private readonly UserOwnedResources $resources)
    {
    }

    public function execute(int $userId, string $termsHash, string $privacyHash): bool
    {
        return $this->resources->constrain(LegalAcceptance::query(), $userId)
            ->where('terms_hash', $termsHash)
            ->where('privacy_hash', $privacyHash)
            ->where('terms_accepted', true)
            ->where('privacy_policy_accepted', true)
            ->where('financial_data_consent', true)
            ->where('is_adult', true)
            ->exists();
    }
}
