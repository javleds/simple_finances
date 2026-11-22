<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Enums\FinancialGoalStatus;
use FinSi\FinanceDomain\Money\Decimal;
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

    public function getAchievedAmount(): string
    {
        return $this->achieved_amount;
    }

    public function getRemainingAmount(): string
    {
        return Decimal::subtract($this->amount, $this->getAchievedAmount());
    }

    protected function casts(): array
    {
        return [
            'user_id'           => 'int',
            'account_id'        => 'int',
            'amount'            => 'decimal:2',
            'achieved_amount'   => 'decimal:2',
            'progress'          => 'float',
            'must_completed_at' => 'date',
            'status'            => FinancialGoalStatus::class,
        ];
    }
}
