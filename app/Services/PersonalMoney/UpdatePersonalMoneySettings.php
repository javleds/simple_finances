<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Dto\PersonalMoneySettingsDto;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\PersonalMoneyReimbursement;
use App\Models\PersonalMoneySetting;
use App\Models\Transaction;
use App\Models\User;

use function array_replace;
use function hash;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

use function max;
use function now;

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
            $payloadHash = hash('sha256', json_encode([$dto->accountId, $dto->openingBalance, $dto->enabled, $dto->startMode, $dto->startedAt], JSON_THROW_ON_ERROR));
            if ('now' === $dto->startMode) {
                if (! $dto->enabled || null === $dto->operationKey) {
                    throw ValidationException::withMessages(['start_mode' => 'Starting from now requires an enabled calculation and an operation key.']);
                }
                $operation = DB::table('personal_money_baseline_operations')->where('user_id', $userId)
                    ->where('operation_key', $dto->operationKey)->first();
                if (null !== $operation) {
                    if ($operation->payload_hash !== $payloadHash) {
                        throw ValidationException::withMessages(['operation_key' => 'This operation key was already used with different settings.']);
                    }

                    return $setting ?? throw ValidationException::withMessages(['operation_key' => 'The original calculation is no longer available.']);
                }
            }
            $eligible = $this->eligibleAccounts->execute($userId)->whereKey($dto->accountId)->lockForUpdate()->exists();
            if (! $eligible && ! ($setting?->account_id === $dto->accountId && ! $dto->enabled)) {
                throw ValidationException::withMessages(['account_id' => 'Choose an individual account owned exclusively by you, without credit or investment tracking.']);
            }
            if (null !== $setting?->activated_at && 'date' === $dto->startMode) {
                throw ValidationException::withMessages(['start_mode' => 'An activated calculation cannot be changed back to a date-based calculation.']);
            }

            $values = [
                'account_id'      => $dto->accountId,
                'started_at'      => $setting?->activated_at ? $setting->started_at : $dto->startedAt,
                'opening_balance' => $dto->openingBalance, 'enabled' => $dto->enabled,
            ];
            if ('now' === $dto->startMode) {
                $activatedAt = now();
                $values = array_replace($values, [
                    'started_at'            => $dto->startedAt ?? $activatedAt->toDateString(), 'activated_at' => $activatedAt,
                    'transaction_cutoff_id' => (int) Transaction::withoutGlobalScopes()->max('id'),
                    'ledger_cutoff_id'      => max((int) AccountMemberLedgerEntry::withoutGlobalScopes()->max('id'), (int) PersonalMoneyReimbursement::query()->max('ledger_entry_key')),
                ]);
                DB::table('personal_money_baseline_operations')->insert([
                    'user_id' => $userId, 'operation_key' => $dto->operationKey, 'payload_hash' => $payloadHash,
                ]);
            }
            if (null === $values['started_at']) {
                throw ValidationException::withMessages(['started_at' => 'Choose a start date or start from now.']);
            }
            if ($dto->enabled) {
                $this->captureExistingReimbursements->execute($userId, (string) $values['started_at']);
            }

            return PersonalMoneySetting::query()->updateOrCreate(['user_id' => $userId], $values);
        }, 3);
    }
}
