<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookReceipt extends Model
{
    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload'      => 'encrypted:array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
