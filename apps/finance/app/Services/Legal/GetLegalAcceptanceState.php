<?php

declare(strict_types=1);

namespace App\Services\Legal;

use FinSi\FinanceDomain\Authorization\HasCurrentLegalAcceptance;
use FinSi\FinanceDomain\Models\User;

class GetLegalAcceptanceState
{
    public function __construct(
        private readonly HasCurrentLegalAcceptance $acceptance,
        private GetLegalDocuments $documents,
    )
    {
    }

    public function execute(User $user): array
    {
        $documents = $this->documents->execute();

        if (! $documents['available']) {
            return ['required' => true, 'available' => false];
        }

        $accepted = $this->acceptance->execute(
            $user->id,
            $documents['documents']['terms']['hash'],
            $documents['documents']['privacy']['hash'],
        );

        return ['required' => ! $accepted, 'available' => true];
    }
}
