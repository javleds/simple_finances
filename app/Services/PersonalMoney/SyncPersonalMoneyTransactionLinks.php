<?php

declare(strict_types=1);

namespace App\Services\PersonalMoney;

use App\Models\PersonalMoneyLink;
use App\Models\Transaction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use function round;

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

        $links->update([
            'cash_effect' => DB::raw('CASE WHEN kind = \'personal_income\' THEN ' . round($transaction->amount, 2) . ' ELSE ' . -round($transaction->amount, 2) . ' END'),
            'concept'     => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
    }
    private const SOURCE_CONTEXT_FIELDS = [
        'account_id', 'type', 'paid_by_user_id', 'payment_source', 'uses_shared_account_rules',
    ];
}
