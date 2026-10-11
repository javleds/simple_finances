<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAttachment extends Model
{
    protected $hidden = ['source_path', 'path', 'reserved_bytes', 'processing_started_at', 'upload_id', 'extension', 'deleted_at'];

    protected function casts(): array
    {
        return ['transaction_id' => 'integer', 'user_id' => 'integer', 'size_bytes' => 'integer', 'reserved_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'processing_started_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
