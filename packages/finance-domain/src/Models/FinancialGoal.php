<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Enums\FinancialGoalStatus;
use FinSi\FinanceDomain\Traits\UnscopedBelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialGoal extends Model
{
    use HasFactory;
    use UnscopedBelongsToUser;

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAchievedAmount(): float
    {
        return $this->achieved_amount;
    }

    public function getRemainingAmount(): float
    {
        return $this->amount - $this->getAchievedAmount();
    }

    protected function casts(): array
    {
        return [
            'user_id'           => 'int',
            'account_id'        => 'int',
            'amount'            => 'float',
            'achieved_amount'   => 'float',
            'progress'          => 'float',
            'must_completed_at' => 'date',
            'status'            => FinancialGoalStatus::class,
        ];
    }
}
