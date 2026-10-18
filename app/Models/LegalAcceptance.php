<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalAcceptance extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'accepted_at'             => 'immutable_datetime',
            'snapshot'                => 'array',
            'terms_accepted'          => 'boolean',
            'privacy_policy_accepted' => 'boolean',
            'financial_data_consent'  => 'boolean',
            'is_adult'                => 'boolean',
        ];
    }
}
