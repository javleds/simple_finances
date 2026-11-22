<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Dto\CreateAccountCustodianDto;
use App\Services\Transaction\SyncAccountMemberLedger;

use function collect;

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function in_array;
use function max;
use function now;
use function round;
use function trim;

class CreateAccountCustodian
{
    public function __construct(
        private readonly BuildAccountCustodySummary $buildAccountCustodySummary,
        private readonly BuildAccountMemberSummary $buildAccountMemberSummary,
        private readonly SyncAccountMemberLedger $syncAccountMemberLedger,
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
    ) {
    }

    public function execute(Account $account, CreateAccountCustodianDto $dto, int $actorId): User
    {
        return DB::transaction(function () use ($account, $dto, $actorId): User {
            $account = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $members = $account->users()->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();

            if ($account->credit_card || $account->virtual) {
                throw ValidationException::withMessages(['account' => 'Los custodios virtuales requieren una cuenta corriente no virtual.']);
            }

            $allowedIds = [...$members, ...$account->virtualCustodians()->whereNull('virtual_retired_at')->pluck('id')->map(static fn ($id): int => (int) $id)->all()];
            $custody = $this->buildAccountCustodySummary->execute($account)->keyBy('user_id');
            $pendingReimbursements = collect($this->buildAccountMemberSummary->execute($account)['pending_reimbursements']);
            $seen = [];

            foreach ($dto->transfers as $transfer) {
                $userId = $transfer['user_id'];
                $available = (int) round((float) ($custody->get($userId)['amount'] ?? 0) * 100);
                $reserved = (int) round((float) $pendingReimbursements
                    ->filter(static fn (array $item): bool => (int) $item['from_user_id'] === $userId && ($item['action_type'] ?? null) === 'custody_to_user')
                    ->sum('amount') * 100);
                $available -= $reserved;
                if (! in_array($userId, $allowedIds, true) || isset($seen[$userId]) || $transfer['amount_cents'] < 0 || $transfer['amount_cents'] > max(0, $available)) {
                    throw ValidationException::withMessages(['transfers' => 'Selecciona custodios de esta cuenta y montos que no superen su custodia disponible después de reservar los reembolsos pendientes.']);
                }
                $seen[$userId] = true;
            }

            if ($dto->openingBalanceCents < 0 || '' === trim($dto->name)) {
                throw ValidationException::withMessages(['opening_balance' => 'El nombre es obligatorio y el saldo adicional no puede ser negativo.']);
            }

            $custodian = new User();
            $custodian->name = trim($dto->name);
            $custodian->email = Str::uuid() . '@custodians.invalid';
            $custodian->password = Str::random(80);
            $custodian->virtual_account_id = $account->id;
            $custodian->save();

            foreach ($dto->transfers as $transfer) {
                if (0 === $transfer['amount_cents']) {
                    continue;
                }

                $amount = $transfer['amount_cents'] / 100;
                foreach ([[$transfer['user_id'], $custodian->id, -$amount], [$custodian->id, $transfer['user_id'], $amount]] as [$userId, $relatedUserId, $entryAmount]) {
                    $account->memberLedgerEntries()->create([
                        'user_id'         => $userId,
                        'related_user_id' => $relatedUserId,
                        'type'            => AccountMemberLedgerEntryType::InternalTransfer,
                        'amount'          => $entryAmount,
                        'description'     => 'Custodia transferida a ' . $custodian->name,
                        'occurred_at'     => now(),
                    ]);
                }
            }

            if ($dto->openingBalanceCents > 0) {
                $income = new Transaction();
                $income->account_id = $account->id;
                $income->user_id = $actorId;
                $income->custodian_user_id = $custodian->id;
                $income->type = TransactionType::Income;
                $income->status = TransactionStatus::Completed;
                $income->uses_shared_account_rules = true;
                $income->virtual_custodian_opening = true;
                $income->uses_virtual_custodian_rules = true;
                $income->concept = 'Saldo inicial de ' . $custodian->name;
                $income->amount = $dto->openingBalanceCents / 100;
                $income->percentage = 100.0;
                $income->scheduled_at = now();
                $income->save();
                $this->syncAccountMemberLedger->execute($income);
                $this->recalculateAccountBalance->execute($account);
            }

            return $custodian;
        });
    }
}
