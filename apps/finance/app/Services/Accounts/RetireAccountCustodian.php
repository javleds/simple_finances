<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use function abs;

use App\Dto\RetireAccountCustodianDto;

use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function in_array;
use function now;
use function round;

class RetireAccountCustodian
{
    public function __construct(private readonly BuildAccountMemberSummary $buildAccountMemberSummary)
    {
    }

    public function execute(Account $account, User $custodian, RetireAccountCustodianDto $dto): void
    {
        DB::transaction(function () use ($account, $custodian, $dto): void {
            $account = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $custodian = $account->virtualCustodians()->whereNull('virtual_retired_at')->whereKey($custodian->id)->lockForUpdate()->firstOrFail();
            $summary = $this->buildAccountMemberSummary->execute($account);
            $custody = (int) round((float) (collect($summary['custody_by_user'])->firstWhere('user_id', $custodian->id)['amount'] ?? 0) * 100);
            $settlement = (float) (collect($summary['settlements_by_user'])->firstWhere('user_id', $custodian->id)['amount'] ?? 0);
            $hasPending = collect($summary['pending_reimbursements'])->contains(static fn (array $item): bool => (int) $item['from_user_id'] === $custodian->id || (int) $item['to_user_id'] === $custodian->id);

            if ($hasPending || abs($settlement) > 0.001 || $custody < 0) {
                throw ValidationException::withMessages(['custodian' => 'Resuelve los reembolsos pendientes y la custodia negativa antes de eliminar este custodio.']);
            }

            $memberIds = $account->users()->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
            $total = 0;
            $seen = [];

            foreach ($dto->distributions as $distribution) {
                $userId = $distribution['user_id'];
                if (! in_array($userId, $memberIds, true) || isset($seen[$userId]) || $distribution['amount_cents'] < 0) {
                    throw ValidationException::withMessages(['distributions' => 'Distribuye la custodia entre miembros vigentes de esta cuenta.']);
                }
                $seen[$userId] = true;
                $total += $distribution['amount_cents'];
            }

            if ($total !== $custody) {
                throw ValidationException::withMessages(['distributions' => 'Debes distribuir el 100% de la custodia antes de eliminar el custodio.']);
            }

            foreach ($dto->distributions as $distribution) {
                if (0 === $distribution['amount_cents']) {
                    continue;
                }

                $amount = $distribution['amount_cents'] / 100;
                foreach ([[$custodian->id, $distribution['user_id'], -$amount], [$distribution['user_id'], $custodian->id, $amount]] as [$userId, $relatedUserId, $entryAmount]) {
                    $account->memberLedgerEntries()->create([
                        'user_id'         => $userId,
                        'related_user_id' => $relatedUserId,
                        'type'            => AccountMemberLedgerEntryType::InternalTransfer,
                        'amount'          => $entryAmount,
                        'description'     => 'Redistribución de custodia de ' . $custodian->name,
                        'occurred_at'     => now(),
                    ]);
                }
            }

            $custodian->virtual_retired_at = now();
            $custodian->save();
        });
    }
}
