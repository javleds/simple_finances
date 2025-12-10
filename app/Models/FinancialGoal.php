<?php

namespace App\Models;

use App\Enums\FinancialGoalStatus;
use App\Traits\UnscopedBelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialGoal extends Model
{
    use HasFactory;
    use UnscopedBelongsToUser;

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'account_id' => 'int',
            'must_completed_at' => 'date',
            'status' => FinancialGoalStatus::class,
        ];
    }

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
        return $this->account->transactions()
            ->completed()
            ->where('financial_goal_id', $this->id)
            ->sum('amount');
    }

    public function getRemainingAmount(): float
    {
        return $this->amount - $this->getAchievedAmount();
    }
}
