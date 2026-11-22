<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\ReadModels;

use function array_column;
use function array_diff;
use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use FinSi\FinanceDomain\Money\Decimal;
use function array_unique;
use function array_values;
use function count;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Category;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Queries\VisibleAccountsForUser;
use FinSi\FinanceDomain\Queries\VisibleCategoriesForUser;
use FinSi\FinanceDomain\Queries\VisibleTransactionsForUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function now;
use function round;

class FinancialReadTools
{
    public function __construct(
        private readonly VisibleAccountsForUser $accounts,
        private readonly VisibleTransactionsForUser $transactions,
        private readonly VisibleCategoriesForUser $categories,
        private readonly BuildAccountMemberSummary $summary,
    ) {
    }

    public function execute(int $actorId, string $tool, array $arguments): array
    {
        $handlers = [
            'find_accounts'            => $this->findAccounts(...),
            'list_account_balances'    => $this->listBalances(...),
            'get_account_balance'      => $this->getBalance(...),
            'get_payables_summary'     => $this->payables(...),
            'get_payable_breakdown'    => $this->breakdown(...),
            'find_categories'          => $this->findCategories(...),
            'summarize_expenses'       => $this->summarizeExpenses(...),
            'list_expenses'            => $this->listExpenses(...),
            'aggregate_expense_groups' => $this->aggregateGroups(...),
        ];
        if (! isset($handlers[$tool])) {
            throw ValidationException::withMessages(['tool' => 'Unknown tool.']);
        }
        $result = DB::transaction(fn (): array => $handlers[$tool]($actorId, $arguments));

        return [
            'schema_version' => 1,
            'period'         => isset($arguments['start_date']) ? ['start_date' => $arguments['start_date'], 'end_date' => $arguments['end_date']] : null,
            'scope'          => $arguments['scope'] ?? 'visible_accounts',
            'currency'       => 'MXN',
            'as_of'          => now()->toIso8601String(),
            'complete'       => true,
            'next_cursor'    => null,
            'warnings'       => [],
            'references'     => [],
            ...$result,
        ];
    }

    private function account(int $actorId, int $id): Account
    {
        return $this->accounts->query($actorId)->findOrFail($id);
    }

    private function balance(Account $account): array
    {
        return ['account_id' => (string) $account->id, 'name' => $account->name, 'balance' => $this->money($account->balance), 'virtual' => (bool) $account->virtual, 'credit_card' => (bool) $account->credit_card];
    }

    private function findAccounts(int $actorId, array $args): array
    {
        $query = $this->accounts->query($actorId);
        if (isset($args['name'])) {
            $query->where('name', 'like', '%' . $args['name'] . '%');
        }

        return $this->accountPage($query, $args);
    }

    private function listBalances(int $actorId, array $args): array
    {
        $query = $this->accounts->query($actorId);
        if (! ($args['include_virtual'] ?? true)) {
            $query->where('virtual', false);
        }

        return $this->accountPage($query, $args);
    }

    private function accountPage(Builder $query, array $args): array
    {
        $limit = (int) ($args['limit'] ?? 100);
        $offset = (int) ($args['cursor'] ?? 0);
        $rows = $query->orderBy('id')->offset($offset)->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $data = $rows->take($limit)->map(fn (Account $a): array => $this->balance($a))->all();

        return ['data' => $data, 'complete' => ! $more, 'next_cursor' => $more ? (string) ($offset + $limit) : null, 'references' => array_map(fn (array $row): array => ['account_id' => $row['account_id']], $data)];
    }

    private function getBalance(int $actorId, array $args): array
    {
        $account = $this->account($actorId, (int) $args['account_id']);

        return ['data' => $this->balance($account), 'references' => [['account_id' => (string) $account->id]]];
    }

    private function debts(Account $account, int $actorId): array
    {
        return array_values(array_filter($this->summary->execute($account)['pending_reimbursements'], fn (array $debt): bool => (int) $debt['from_user_id'] === $actorId));
    }

    private function payables(int $actorId, array $args): array
    {
        $query = $this->accounts->query($actorId);
        if (isset($args['account_id'])) {
            $this->account($actorId, (int) $args['account_id']);
            $query->whereKey($args['account_id']);
        }
        $rows = [];
        $total = '0.00';
        $references = [];
        foreach ($query->orderBy('id')->cursor() as $account) {
            $references[] = ['account_id' => (string) $account->id];
            $amount = Decimal::sum($this->debts($account, $actorId), 'amount');
            $total = Decimal::add($total, $amount);
            if (Decimal::compare($amount, 0) > 0) {
                $rows[] = ['account_id' => (string) $account->id, 'name' => $account->name, 'amount' => $this->money($amount)];
            }
        }

        return ['data' => ['accounts' => $rows, 'total' => $this->money($total)], 'references' => $references];
    }

    private function breakdown(int $actorId, array $args): array
    {
        $account = $this->account($actorId, (int) $args['account_id']);
        $debts = $this->debts($account, $actorId);
        $rows = [];
        foreach ($debts as $debt) {
            $attributed = '0.00';
            foreach ($debt['items'] as $item) {
                if (($item['attribution'] ?? null) === 'estimated') {
                    continue;
                }
                $attributed = Decimal::add($attributed, $item['amount']);
                $rows[] = ['account_id' => (string) $account->id, 'transaction_id' => $item['transaction_id'], 'description' => $item['concept'], 'date' => $item['occurred_at'], 'amount' => $this->money($item['amount']), 'kind' => 'transaction', 'action_type' => $debt['action_type'] ?? 'user_to_user'];
            }
            $adjustment = Decimal::subtract($debt['amount'], $attributed);
            if (Decimal::compare($adjustment, 0) !== 0) {
                $rows[] = ['account_id' => (string) $account->id, 'transaction_id' => null, 'description' => 'Saldo pendiente sin atribución exacta a una transacción', 'date' => null, 'amount' => $this->money($adjustment), 'kind' => 'adjustment', 'action_type' => $debt['action_type'] ?? 'user_to_user'];
            }
        }
        $offset = (int) ($args['cursor'] ?? 0);
        $limit = (int) ($args['limit'] ?? 100);
        $page = array_slice($rows, $offset, $limit);
        $more = count($rows) > $offset + $limit;

        return ['data' => ['account_id' => (string) $account->id, 'total' => $this->money(Decimal::sum($debts, 'amount')), 'items' => $page], 'complete' => ! $more, 'next_cursor' => $more ? (string) ($offset + $limit) : null, 'references' => [['account_id' => (string) $account->id], ...array_map(fn (array $row): array => array_filter(['account_id' => $row['account_id'], 'transaction_id' => $row['transaction_id']]), $page)]];
    }

    private function findCategories(int $actorId, array $args): array
    {
        $account = isset($args['account_id']) ? $this->account($actorId, (int) $args['account_id']) : null;
        $query = $this->categoryCatalog($actorId, $account);
        if (isset($args['name'])) {
            $query->where('name', 'like', '%' . $args['name'] . '%');
        }
        $limit = (int) ($args['limit'] ?? 100);
        $offset = (int) ($args['cursor'] ?? 0);
        $rows = $query->orderBy('id')->offset($offset)->limit($limit + 1)->get(['id', 'name', 'account_id']);
        $more = $rows->count() > $limit;

        return ['data' => $rows->take($limit)->map(fn ($row): array => ['category_id' => (string) $row->id, 'name' => $row->name, 'account_id' => $row->account_id ? (string) $row->account_id : null])->all(), 'complete' => ! $more, 'next_cursor' => $more ? (string) ($offset + $limit) : null, 'references' => $account ? [['account_id' => (string) $account->id]] : $rows->take($limit)->pluck('account_id')->filter()->unique()->map(fn ($id): array => ['account_id' => (string) $id])->values()->all()];
    }

    private function categoryCatalog(int $actorId, ?Account $account = null): Builder
    {
        if (null !== $account) {
            return $this->categories->query($actorId, $account);
        }

        return Category::query()->where(function (Builder $query) use ($actorId): void {
            $query->whereIn('id', $this->categories->query($actorId)->select('id'))
                ->orWhereIn('account_id', $this->accounts->query($actorId)->where('uses_shared_categories', true)->select('id'));
        });
    }

    private function expenses(int $actorId, array $args): Builder
    {
        $start = Carbon::createFromFormat('!Y-m-d', $args['start_date']);
        $end = Carbon::createFromFormat('!Y-m-d', $args['end_date']);
        if ($start->gt($end) || $start->diffInDays($end) > 3660) {
            throw ValidationException::withMessages(['period' => 'Use an ordered period of at most ten years.']);
        }
        $accountIds = $args['account_ids'] ?? [];
        foreach ($accountIds as $id) {
            $this->account($actorId, (int) $id);
        }
        $query = $this->transactions->query($actorId)->completed()->outcome()->whereNull('parent_transaction_id')->whereBetween('scheduled_at', [$start, $end->endOfDay()]);
        if ([] !== $accountIds) {
            $query->whereIn('account_id', $accountIds);
        }
        if ([] === $accountIds) {
            $query->whereHas('account', fn (Builder $q): Builder => $q->where('virtual', false));
        }
        if (! empty($args['category_ids'])) {
            $allowed = $this->categoryCatalog($actorId)->whereIn('id', $args['category_ids'])->pluck('id')->all();
            if ([] !== array_diff($args['category_ids'], $allowed)) {
                throw ValidationException::withMessages(['category_ids' => 'Category not available.']);
            }
            $query->whereIn('category_id', $args['category_ids']);
        }

        return $query;
    }

    private function expenseReferences(Builder $query): array
    {
        return (clone $query)->reorder()->distinct()->pluck('account_id')->map(fn ($id): array => ['account_id' => (string) $id])->all();
    }

    private function listExpenses(int $actorId, array $args): array
    {
        $query = $this->expenses($actorId, $args);
        $total = (string) (clone $query)->sum('amount');
        $limit = (int) ($args['limit'] ?? 100);
        $offset = (int) ($args['cursor'] ?? 0);
        $rows = $query->with('category')->orderBy('id')->offset($offset)->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $items = $rows->take($limit)->map(fn (Transaction $t): array => ['transaction_id' => (string) $t->id, 'account_id' => (string) $t->account_id, 'date' => $t->scheduled_at->toDateString(), 'description' => $t->concept, 'category_id' => $t->category_id ? (string) $t->category_id : null, 'category' => $t->category?->name, 'amount' => $this->money($t->amount)])->all();

        return ['data' => ['total' => $this->money($total), 'items' => $items], 'complete' => ! $more, 'next_cursor' => $more ? (string) ($offset + $limit) : null, 'references' => $this->expenseReferences($this->expenses($actorId, $args))];
    }

    private function summarizeExpenses(int $actorId, array $args): array
    {
        $query = $this->expenses($actorId, $args);
        $groupBy = $args['group_by'] ?? 'category';
        $month = 'sqlite' === $query->getConnection()->getDriverName()
            ? "strftime('%Y-%m', scheduled_at)"
            : "DATE_FORMAT(scheduled_at, '%Y-%m')";
        $expressions = ['account' => 'account_id', 'category' => 'category_id', 'month' => $month, 'concept' => 'concept'];
        $expression = $expressions[$groupBy];
        $total = (string) (clone $query)->sum('amount');
        $limit = (int) ($args['limit'] ?? 100);
        $offset = (int) ($args['cursor'] ?? 0);
        $rows = (clone $query)->selectRaw($expression . ' as group_key, SUM(amount) as total_amount, COUNT(*) as total_count')
            ->groupByRaw($expression)->orderByDesc('total_amount')->orderBy('group_key')
            ->offset($offset)->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $keys = $rows->take($limit)->pluck('group_key')->filter()->all();
        $labels = [];
        if ('account' === $groupBy) {
            $labels = $this->accounts->query($actorId)->whereKey($keys)->pluck('name', 'id')->all();
        }
        if ('category' === $groupBy) {
            $labels = Category::query()->whereKey($keys)->pluck('name', 'id')->all();
        }
        $data = $rows->take($limit)->map(fn ($row): array => [
            'key'    => (string) ($row->group_key ?? 'uncategorized'),
            'label'  => $labels[$row->group_key] ?? (string) ($row->group_key ?? 'Sin categoría'),
            'amount' => $this->money((string) $row->total_amount),
            'count'  => (int) $row->total_count,
        ])->all();

        return ['data' => ['total' => $this->money($total), 'groups' => $data], 'complete' => ! $more, 'next_cursor' => $more ? (string) ($offset + $limit) : null, 'references' => $this->expenseReferences($this->expenses($actorId, $args))];
    }

    private function aggregateGroups(int $actorId, array $args): array
    {
        $ids = array_merge(...array_column($args['groups'], 'transaction_ids'));
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['groups' => 'Transactions must appear in only one group.']);
        }
        $query = $this->expenses($actorId, $args)->whereIn('id', $ids);
        $rows = $query->get()->keyBy('id');
        if ($rows->count() !== count($ids)) {
            throw ValidationException::withMessages(['groups' => 'Transactions are outside the authorized report.']);
        }
        $data = [];
        foreach ($args['groups'] as $group) {
            $amount = '0.00';
            foreach ($group['transaction_ids'] as $id) {
                $amount = Decimal::add($amount, $rows[$id]->amount);
            }
            $data[] = ['label' => $group['label'], 'classification' => 'inferred', 'amount' => $this->money($amount), 'transaction_ids' => array_map('strval', $group['transaction_ids'])];
        }

        return ['data' => $data, 'references' => $rows->map(fn (Transaction $t): array => ['account_id' => (string) $t->account_id, 'transaction_id' => (string) $t->id])->values()->all()];
    }

    private function money(int | string | null $amount): string
    {
        return Decimal::normalize($amount ?? '0.00');
    }
}
