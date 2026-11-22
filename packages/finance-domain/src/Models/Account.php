<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use FinSi\FinanceDomain\Money\Decimal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use function sprintf;

class Account extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $hidden = ['spent_legacy', 'spent_exact'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(AccountMember::class)->withPivot(['percentage']);
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

    public function getBalanceUntilNow(): string
    {
        return Decimal::subtract(
            (string) $this->transactions()->untilNow()->completed()->income()->sum('amount'),
            (string) $this->transactions()->untilNow()->completed()->outcome()->sum('amount'),
        );
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
            Decimal::compare($balance ?? '0.00', 0) >= 0 ? '' : '-',
            Decimal::format(Decimal::abs($balance ?? '0.00'))
        );
    }

    protected function casts(): array
    {
        return [
            'user_id'                => 'integer',
            'balance'                => 'decimal:2',
            'credit_card'            => 'bool',
            'credit_line'            => 'decimal:2',
            'cutoff_day'             => 'int',
            'next_cutoff_date'       => 'datetime',
            'available_credit'       => 'decimal:2',
            'spent'                  => 'decimal:2',
            'feed_account_id'        => 'int',
            'virtual'                => 'bool',
            'uses_shared_categories' => 'bool',
        ];
    }
}
