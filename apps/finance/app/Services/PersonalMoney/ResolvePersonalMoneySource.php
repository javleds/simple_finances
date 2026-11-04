<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use FinSi\FinanceDomain\Queries\EligiblePersonalMoneyAccounts;
use FinSi\FinanceDomain\Queries\UserOwnedResources;
use function abort_unless;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\PersonalMoneyLink;
use FinSi\FinanceDomain\Models\PersonalMoneyReimbursement;
use FinSi\FinanceDomain\Models\PersonalMoneySetting;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolvePersonalMoneySource
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly EligiblePersonalMoneyAccounts $eligibleAccounts,
        private readonly BuildPersonalMoneySources $buildSources,
    ) {
    }

    public function execute(int $userId, string $sourceType, int $sourceId, string $resolution): void
    {
        DB::transaction(function () use ($userId, $sourceType, $sourceId, $resolution): void {
            $sourceQuery = 'transaction' === $sourceType
                ? Transaction::withoutGlobalScopes()
                : $this->userOwnedResources->constrain(PersonalMoneyReimbursement::query(), (int) $userId);
            $record = (clone $sourceQuery)->whereKey($sourceId)->firstOrFail();
            Account::withoutGlobalScopes()->whereKey($record->account_id)->lockForUpdate()->firstOrFail();
            $record = $sourceQuery->whereKey($sourceId)->lockForUpdate()->firstOrFail();
            $setting = $this->userOwnedResources->constrain(PersonalMoneySetting::query(), (int) $userId)->lockForUpdate()->first();
            if (! $setting?->enabled || ! $this->eligibleAccounts->execute($userId)->whereKey($setting->account_id)->exists()) {
                throw ValidationException::withMessages(['source_id' => 'Enable an eligible personal fund before resolving movements.']);
            }

            $source = $this->buildSources->execute($userId, $setting)->first(
                static fn (array $item): bool => $item['source_type'] === $sourceType && $item['source_id'] === $sourceId,
            );
            abort_unless(null !== $source, 404);
            if ('automatic' === $source['resolution']) {
                throw ValidationException::withMessages(['source_id' => 'Personal account movements are included automatically.']);
            }

            $key = [
                'user_id'          => $userId,
                'transaction_id'   => 'transaction' === $sourceType ? $sourceId : null,
                'reimbursement_id' => 'reimbursement' === $sourceType ? $sourceId : null,
            ];
            if ('contribution' === $source['kind'] && 'personal' === $resolution
                && PersonalMoneyLink::query()->where('transaction_id', $sourceId)->where('user_id', '!=', $userId)
                    ->where('resolution', 'personal')->where('source_signature', $source['source_signature'])->exists()) {
                throw ValidationException::withMessages(['source_id' => 'This contribution cannot be linked to another personal fund. Record separate contributions for separate sources.']);
            }

            PersonalMoneyLink::query()->updateOrCreate($key, [
                'resolution'        => $resolution, 'source_signature' => $source['source_signature'],
                'source_account_id' => $source['source_account_id'], 'account_name' => $source['account_name'],
                'concept'           => $source['concept'], 'occurred_at' => $source['occurred_at'],
                'cash_effect'       => $source['raw_effect'], 'kind' => $source['kind'],
            ]);
        }, 3);
    }
}
