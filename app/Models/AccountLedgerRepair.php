<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLedgerRepair extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'actor_user_id',
        'target_transaction_id',
        'reverses_repair_id',
        'reversed_by_repair_id',
        'status',
        'issue_code',
        'repair_type',
        'confidence',
        'input_payload',
        'evidence_payload',
        'preview_payload',
        'result_payload',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'actor_user_id' => 'integer',
            'target_transaction_id' => 'integer',
            'reverses_repair_id' => 'integer',
            'reversed_by_repair_id' => 'integer',
            'input_payload' => 'array',
            'evidence_payload' => 'array',
            'preview_payload' => 'array',
            'result_payload' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'target_transaction_id');
    }

    public function reversesRepair(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_repair_id');
    }

    public function reversedByRepair(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_repair_id');
    }
}
