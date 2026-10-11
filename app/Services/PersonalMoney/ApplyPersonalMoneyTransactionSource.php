<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionType;
use App\Models\PersonalMoneySetting;
use App\Models\Transaction;

use function now;

class ApplyPersonalMoneyTransactionSource
{
    public function __construct(
        private readonly ResolvePersonalMoneySource $resolveSource,
        private readonly EligiblePersonalMoneyAccounts $eligibleAccounts,
    ) {
    }

    public function execute(Transaction $transaction, int $userId, ?string $resolution, bool $creating): void
    {
        if (null !== $resolution) {
            $this->resolveSource->execute($userId, 'transaction', $transaction->id, $resolution);

            return;
        }
        if (! $creating || true !== $transaction->uses_shared_account_rules
            || TransactionType::Outcome !== $transaction->type
            || TransactionPaymentSource::MemberOutOfPocket !== $transaction->payment_source
            || $transaction->paid_by_user_id !== $userId) {
            return;
        }
        $setting = PersonalMoneySetting::query()->where('user_id', $userId)->lockForUpdate()->first();
        if (! $setting?->enabled || $transaction->scheduled_at->lt($setting->started_at)
            || $transaction->scheduled_at->gt(now())
            || (null !== $setting->activated_at && $transaction->id <= $setting->transaction_cutoff_id)
            || ! $this->eligibleAccounts->execute($userId)->whereKey($setting->account_id)->exists()) {
            return;
        }
        $this->resolveSource->execute($userId, 'transaction', $transaction->id, 'personal');
    }
}
