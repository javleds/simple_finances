<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Models\LegalAcceptance;
use App\Models\User;

class GetLegalAcceptanceState
{
    public function __construct(private GetLegalDocuments $documents)
    {
    }

    public function execute(User $user): array
    {
        $documents = $this->documents->execute();

        if (! $documents['available']) {
            return ['required' => true, 'available' => false];
        }

        $accepted = LegalAcceptance::query()->where('user_id', $user->id)
            ->where('terms_hash', $documents['documents']['terms']['hash'])
            ->where('privacy_hash', $documents['documents']['privacy']['hash'])
            ->where('terms_accepted', true)->where('privacy_policy_accepted', true)
            ->where('financial_data_consent', true)->where('is_adult', true)->exists();

        return ['required' => ! $accepted, 'available' => true];
    }
}
