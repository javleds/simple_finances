<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAttachment extends Model
{
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    protected $hidden = ['source_path', 'path', 'reserved_bytes', 'processing_started_at', 'upload_id', 'extension', 'deleted_at'];

    protected function casts(): array
    {
        return ['transaction_id' => 'integer', 'user_id' => 'integer', 'size_bytes' => 'integer', 'reserved_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'processing_started_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime'];
    }
}
