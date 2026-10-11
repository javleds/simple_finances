<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Dto\PersonalMoneySettingsDto;
use App\Models\Account;
use App\Models\PersonalMoneySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePersonalMoneySettings
{
    public function __construct(
        private readonly EligiblePersonalMoneyAccounts $eligibleAccounts,
        private readonly CaptureExistingPersonalMoneyReimbursements $captureExistingReimbursements,
    ) {
    }

    public function execute(int $userId, PersonalMoneySettingsDto $dto): PersonalMoneySetting
    {
        return DB::transaction(function () use ($userId, $dto): PersonalMoneySetting {
            User::withoutGlobalScopes()->whereKey($userId)->lockForUpdate()->firstOrFail();
            Account::withoutGlobalScopes()->whereKey($dto->accountId)->lockForUpdate()->firstOrFail();
            $setting = PersonalMoneySetting::query()->where('user_id', $userId)->lockForUpdate()->first();
            $eligible = $this->eligibleAccounts->execute($userId)->whereKey($dto->accountId)->lockForUpdate()->exists();
            if (! $eligible && ! ($setting?->account_id === $dto->accountId && ! $dto->enabled)) {
                throw ValidationException::withMessages(['account_id' => 'Choose an individual account owned exclusively by you, without credit or investment tracking.']);
            }

            if ($dto->enabled) {
                $this->captureExistingReimbursements->execute($userId, $dto->startedAt);
            }

            return PersonalMoneySetting::query()->updateOrCreate(['user_id' => $userId], [
                'account_id'      => $dto->accountId, 'started_at' => $dto->startedAt,
                'opening_balance' => $dto->openingBalance, 'enabled' => $dto->enabled,
            ]);
        }, 3);
    }
}
