<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Dto\PersonalMoneySettingsDto;

use function array_replace;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\PersonalMoneyReimbursement;
use FinSi\FinanceDomain\Models\PersonalMoneySetting;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Queries\EligiblePersonalMoneyAccounts;
use FinSi\FinanceDomain\Queries\UserOwnedResources;

use function hash;
use function hash_equals;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function is_finite;
use function json_encode;

use const JSON_THROW_ON_ERROR;

use function max;
use function now;
use function sprintf;

class UpdatePersonalMoneySettings
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly EligiblePersonalMoneyAccounts $eligibleAccounts,
        private readonly CaptureExistingPersonalMoneyReimbursements $captureExistingReimbursements,
    ) {
    }

    public function execute(int $userId, PersonalMoneySettingsDto $dto): PersonalMoneySetting
    {
        return DB::transaction(function () use ($userId, $dto): PersonalMoneySetting {
            User::withoutGlobalScopes()->whereKey($userId)->lockForUpdate()->firstOrFail();
            Account::withoutGlobalScopes()->whereKey($dto->accountId)->lockForUpdate()->firstOrFail();
            $setting = $this->userOwnedResources->constrain(PersonalMoneySetting::query(), (int) $userId)->lockForUpdate()->first();
            $payloadHash = $this->payloadHash($dto, Decimal::normalize($dto->openingBalance));
            if ('now' === $dto->startMode) {
                if (! $dto->enabled || null === $dto->operationKey) {
                    throw ValidationException::withMessages(['start_mode' => 'Starting from now requires an enabled calculation and an operation key.']);
                }
                $operation = DB::table('personal_money_baseline_operations')->where('user_id', $userId)
                    ->where('operation_key', $dto->operationKey)->first();
                if (null !== $operation) {
                    if (! $this->matchesOperation($dto, $operation->payload_hash, (int) $operation->payload_hash_version, $payloadHash)) {
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
                    'user_id' => $userId, 'operation_key' => $dto->operationKey, 'payload_hash' => $payloadHash, 'payload_hash_version' => 2,
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

    private function payloadHash(PersonalMoneySettingsDto $dto, string | float $amount): string
    {
        return hash('sha256', json_encode([$dto->accountId, $amount, $dto->enabled, $dto->startMode, $dto->startedAt], JSON_THROW_ON_ERROR));
    }

    private function matchesOperation(PersonalMoneySettingsDto $dto, string $storedHash, int $version, string $canonicalHash): bool
    {
        if (2 === $version) {
            return hash_equals($storedHash, $canonicalHash);
        }
        if (1 !== $version) {
            return false;
        }

        $canonical = Decimal::normalize($dto->openingBalance);
        $legacy = (float) $canonical;
        if (! is_finite($legacy) || sprintf('%.2F', $legacy) !== $canonical) {
            return false;
        }
        if (hash_equals($storedHash, $this->payloadHash($dto, $legacy))) {
            return true;
        }

        return '0.00' === $canonical && hash_equals($storedHash, $this->payloadHash($dto, -0.0));
    }
}
