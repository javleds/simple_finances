<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use function abs;

use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\PersonalMoneyLink;
use App\Models\PersonalMoneyReimbursement;
use App\Models\PersonalMoneySetting;
use App\Models\Transaction;
use App\Services\Transaction\VisibleTransactionsForUser;

use function hash;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

use function implode;
use function in_array;
use function now;
use function round;
use function str_pad;

use const STR_PAD_LEFT;

class BuildPersonalMoneySources
{
    public function __construct(private readonly VisibleTransactionsForUser $visibleTransactionsForUser)
    {
    }

    public function execute(int $userId, PersonalMoneySetting $setting): Collection
    {
        $links = PersonalMoneyLink::query()->where('user_id', $userId)->get()
            ->keyBy(static fn (PersonalMoneyLink $link): string => (null !== $link->transaction_id ? 'transaction:' . $link->transaction_id : 'reimbursement:' . $link->reimbursement_id));

        $transactions = $this->visibleTransactionsForUser->query($userId)
            ->completed()->whereNull('legacy_migrated_at')->whereNull('parent_transaction_id')
            ->whereNull('account_balance_snapshot_id')
            ->where('scheduled_at', '<=', now())
            ->when(null !== $setting->activated_at,
                static fn (Builder $query): Builder => $query->where('transactions.id', '>', $setting->transaction_cutoff_id),
                static fn (Builder $query): Builder => $query->where('scheduled_at', '>=', $setting->started_at->startOfDay()))
            ->where(static function (Builder $query) use ($setting, $userId): void {
                $query->where(static fn (Builder $personal): Builder => $personal->where('account_id', $setting->account_id)->where(static fn (Builder $rules): Builder => $rules->where('uses_shared_account_rules', false)->orWhereNull('uses_shared_account_rules')))
                    ->orWhere(static function (Builder $shared) use ($userId): void {
                        $shared->where(static function (Builder $rules): void {
                            $rules->where('uses_shared_account_rules', true)
                                ->orWhere(static fn (Builder $legacy): Builder => $legacy->whereNull('uses_shared_account_rules')->whereHas('account.users', null, '>', 1));
                        })->where(static function (Builder $movements) use ($userId): void {
                            $movements->where('type', TransactionType::Income)
                                ->orWhere(static fn (Builder $outcomes): Builder => $outcomes->where('type', TransactionType::Outcome)
                                    ->where('payment_source', TransactionPaymentSource::MemberOutOfPocket)
                                    ->where('paid_by_user_id', $userId));
                        });
                    });
            })->with('account')->get();

        $sources = $transactions->map(function (Transaction $transaction) use ($setting, $links): array {
            $personal = $transaction->account_id === $setting->account_id && true !== $transaction->uses_shared_account_rules;
            $income = TransactionType::Income === $transaction->type;
            $kind = $personal ? ($income ? 'personal_income' : 'personal_expense') : ($income ? 'contribution' : 'out_of_pocket');
            $signature = hash('sha256', implode(':', [
                $transaction->account_id, $transaction->type->value,
                $transaction->paid_by_user_id, $transaction->payment_source?->value,
                null === $transaction->uses_shared_account_rules ? 'legacy' : (int) $transaction->uses_shared_account_rules,
            ]));

            return $this->source(
                type: 'transaction', id: $transaction->id, accountId: $transaction->account_id, accountName: $transaction->account->name,
                concept: $transaction->concept, occurredAt: $transaction->scheduled_at->toIso8601String(),
                amount: $transaction->amount, kind: $kind, signature: $signature,
                rawEffect: $personal && $income ? $transaction->amount : -$transaction->amount,
                automatic: $personal && ! (null !== $setting->activated_at && $transaction->scheduled_at->lt($setting->started_at)), link: $links->get('transaction:' . $transaction->id),
            );
        });

        $reimbursements = PersonalMoneyReimbursement::query()
            ->where('user_id', $userId)->where('cash_effect', '!=', 0)
            ->where('occurred_at', '<=', now())
            ->when(null !== $setting->activated_at,
                static fn (Builder $query): Builder => $query->where('ledger_entry_key', '>', $setting->ledger_cutoff_id),
                static fn (Builder $query): Builder => $query->where('occurred_at', '>=', $setting->started_at->startOfDay()))
            ->get();

        foreach ($reimbursements as $reimbursement) {
            $signature = hash('sha256', implode(':', [$reimbursement->account_id, $reimbursement->user_id, 'reimbursement']));
            $sources->push($this->source(
                type: 'reimbursement', id: $reimbursement->id, accountId: $reimbursement->account_id, accountName: $reimbursement->account_name,
                concept: $reimbursement->description, occurredAt: $reimbursement->occurred_at->toIso8601String(),
                amount: abs($reimbursement->cash_effect), kind: 'reimbursement', signature: $signature,
                rawEffect: $reimbursement->cash_effect, automatic: false, link: $links->get('reimbursement:' . $reimbursement->id),
            ));
        }

        $memberAccountIds = Account::withoutGlobalScopes()->whereNull('deleted_at')
            ->whereHas('users', static fn (Builder $users): Builder => $users->where('users.id', $userId))->pluck('id');
        foreach ($links as $link) {
            if (null === $link->transaction_id || $memberAccountIds->contains($link->source_account_id)
                || (null !== $setting->activated_at ? $link->transaction_id <= $setting->transaction_cutoff_id : $link->occurred_at->lt($setting->started_at))
                || $link->occurred_at->gt(now())) {
                continue;
            }
            $sources->push($this->source(
                type: 'transaction', id: $link->transaction_id, accountId: $link->source_account_id, accountName: $link->account_name,
                concept: $link->concept, occurredAt: $link->occurred_at->toIso8601String(),
                amount: abs($link->cash_effect), kind: $link->kind, signature: $link->source_signature,
                rawEffect: $link->cash_effect, automatic: false, link: $link,
            ));
        }

        return $sources->sortByDesc(static fn (array $source): string => $source['occurred_at'] . ':' . str_pad((string) $source['source_id'], 20, '0', STR_PAD_LEFT))->values();
    }

    private function source(
        string $type, int $id, int $accountId, string $accountName, string $concept, string $occurredAt,
        float $amount, string $kind, string $signature, float $rawEffect, bool $automatic,
        ?PersonalMoneyLink $link,
    ): array {
        $resolution = $automatic ? 'automatic' : 'pending';
        if (! $automatic && $link?->source_signature === $signature) {
            $resolution = $link->resolution;
        }

        return [
            'source_account_id' => $accountId,
            'source_type'       => $type, 'source_id' => $id, 'account_name' => $accountName,
            'concept'           => $concept, 'occurred_at' => $occurredAt, 'amount' => round($amount, 2),
            'kind'              => $kind, 'resolution' => $resolution,
            'raw_effect'        => round($rawEffect, 2),
            'effect'            => in_array($resolution, ['personal', 'automatic'], true) ? round($rawEffect, 2) : 0.0,
            'source_signature'  => $signature,
        ];
    }
}
