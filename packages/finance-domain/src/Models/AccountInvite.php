<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Money\Decimal;

use FinSi\FinanceDomain\Enums\InviteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountInvite extends Model
{
    use HasFactory;

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function unscopedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unscopedUser(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function isPending(): bool
    {
        return InviteStatus::Pending === $this->status;
    }

    public function isAccepted(): bool
    {
        return InviteStatus::Accepted === $this->status;
    }

    public function isDeclined(): bool
    {
        return InviteStatus::Declined === $this->status;
    }

    public function isOwnerAccount(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function hasPercentageAssigned(): bool
    {
        return Decimal::compare($this->percentage, 0) > 0;
    }

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'status'     => InviteStatus::class,
            'percentage' => 'decimal:2',
        ];
    }
}
