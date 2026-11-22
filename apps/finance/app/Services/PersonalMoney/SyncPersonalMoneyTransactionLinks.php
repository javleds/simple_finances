<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use FinSi\FinanceDomain\Models\PersonalMoneyLink;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Query\Builder;

class SyncPersonalMoneyTransactionLinks
{
    public function execute(Transaction $transaction, Transaction $previousTransaction): void
    {
        $links = PersonalMoneyLink::query()->where('transaction_id', $transaction->id)
            ->whereExists(static function (Builder $query): void {
                $query->selectRaw('1')->from('account_user')
                    ->whereColumn('account_user.account_id', 'personal_money_links.source_account_id')
                    ->whereColumn('account_user.user_id', 'personal_money_links.user_id');
            });
        if ($transaction->only(self::SOURCE_CONTEXT_FIELDS) !== $previousTransaction->only(self::SOURCE_CONTEXT_FIELDS)) {
            $links->delete();

            return;
        }

        $values = [
            'concept'     => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ];
        (clone $links)->where('kind', 'personal_income')->update([
            ...$values, 'cash_effect' => Decimal::normalize($transaction->amount),
        ]);
        (clone $links)->where('kind', '!=', 'personal_income')->update([
            ...$values, 'cash_effect' => Decimal::negate($transaction->amount),
        ]);
    }
    private const SOURCE_CONTEXT_FIELDS = [
        'account_id', 'type', 'paid_by_user_id', 'payment_source', 'uses_shared_account_rules',
    ];
}
