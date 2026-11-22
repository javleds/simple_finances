<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use function abs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use function number_format;
use function sprintf;

class Account extends Model
{
    use HasFactory;
    use SoftDeletes;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['percentage']);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function virtualCustodians(): HasMany
    {
        return $this->hasMany(User::class, 'virtual_account_id');
    }

    public function usesSharedAccountRules(): bool
    {
        return $this->users()->count() > 1
            || $this->virtualCustodians()->whereNull('virtual_retired_at')->exists();
    }

    public function memberLedgerEntries(): HasMany
    {
        return $this->hasMany(AccountMemberLedgerEntry::class);
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(AccountBalanceSnapshot::class);
    }

    public function feedAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'feed_account_id', 'id');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(AccountInvite::class);
    }

    public function seedAccounts(): HasMany
    {
        return $this->hasMany(self::class, 'feed_account_id', 'id');
    }

    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    public function getBalanceUntilNow(): float
    {
        return
            $this->transactions()->untilNow()->completed()->income()->sum('amount')
            - $this->transactions()->untilNow()->completed()->outcome()->sum('amount')
            ?? 0.0;
    }

    public function isCreditCard(): bool
    {
        return $this->credit_card;
    }

    public function getTransferBalanceLabelAttribute(): string
    {
        $balance = $this->credit_card ? $this->available_credit : $this->balance;

        return sprintf(
            '%s [%s$ %s]',
            $this->name,
            $balance >= 0 ? '' : '-',
            number_format(abs($balance), 2)
        );
    }

    protected function casts(): array
    {
        return [
            'user_id'                => 'integer',
            'balance'                => 'float',
            'credit_card'            => 'bool',
            'credit_line'            => 'float',
            'cutoff_day'             => 'int',
            'next_cutoff_date'       => 'datetime',
            'available_credit'       => 'float',
            'spent'                  => 'float',
            'feed_account_id'        => 'int',
            'virtual'                => 'bool',
            'uses_shared_categories' => 'bool',
        ];
    }
}
