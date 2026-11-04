<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use FinSi\FinanceDomain\Queries\EligiblePersonalMoneyAccounts;
use FinSi\FinanceDomain\Queries\UserOwnedResources;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\PersonalMoneySetting;
use FinSi\FinanceDomain\Models\Transaction;

use function now;

class ApplyPersonalMoneyTransactionSource
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
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
        $setting = $this->userOwnedResources->constrain(PersonalMoneySetting::query(), (int) $userId)->lockForUpdate()->first();
        if (! $setting?->enabled || $transaction->scheduled_at->lt($setting->started_at)
            || $transaction->scheduled_at->gt(now())
            || (null !== $setting->activated_at && $transaction->id <= $setting->transaction_cutoff_id)
            || ! $this->eligibleAccounts->execute($userId)->whereKey($setting->account_id)->exists()) {
            return;
        }
        $this->resolveSource->execute($userId, 'transaction', $transaction->id, 'personal');
    }
}
