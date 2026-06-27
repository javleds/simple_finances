<?php

namespace App\Http\Controllers\Api;

use App\Dto\TransactionFormDto;
use App\Http\Requests\Api\TransactionIndexRequest;
use App\Http\Requests\Api\TransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Transaction\BuildTransactionFacilityQuery;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Http\JsonResponse;

class TransactionController extends ApiController
{
    public function index(
        TransactionIndexRequest $request,
        BuildTransactionFacilityQuery $buildTransactionFacilityQuery,
    ): JsonResponse
    {
        $query = $buildTransactionFacilityQuery->execute(
            $request->validated(),
            $request->user()->id,
        );
        $summary = $this->summary($query);
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $paginator = $query->paginate($perPage)->withQueryString();

        return $this->respond([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'summary' => $summary,
            ],
        ]);
    }

    public function store(TransactionRequest $request, TransactionCreator $transactionCreator): JsonResponse
    {
        $transaction = $transactionCreator->execute(
            TransactionFormDto::fromFormArray($request->validated())
        );

        return $this->respondModel(
            $transaction->fresh(),
            ['account', 'user', 'financialGoal', 'subTransactions'],
            201,
            $this->transactionAccountMeta($transaction->account_id),
        );
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return $this->respondModel($transaction, ['account', 'user', 'financialGoal', 'subTransactions']);
    }

    public function update(
        TransactionRequest $request,
        Transaction $transaction,
        TransactionUpdater $transactionUpdater,
    ): JsonResponse {
        abort_unless($transaction->user_id === $request->user()->id, 403);

        $previousAccountId = $transaction->account_id;
        $payload = $request->validated();
        $payload['id'] = $transaction->id;

        $transaction = $transactionUpdater->execute(
            $transaction,
            TransactionFormDto::fromFormArray($payload)
        );

        return $this->respondModel(
            $transaction->fresh(),
            ['account', 'user', 'financialGoal', 'subTransactions'],
            meta: $this->transactionAccountMeta($transaction->account_id, $previousAccountId),
        );
    }

    public function delete(Transaction $transaction, TransactionRemover $transactionRemover): JsonResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 403);

        $accountId = $transaction->account_id;
        $subTransactionIds = $transactionRemover->execute($transaction);
        $meta = $this->transactionAccountMeta($accountId);
        $meta['subtransactions'] = $subTransactionIds;

        return $this->respondDeleted(
            'Transaction deleted successfully.',
            $meta,
        );
    }

    private function transactionAccountMeta(int $accountId, ?int $previousAccountId = null): array
    {
        $meta = [
            'account' => $this->accountMeta($accountId),
        ];

        if ($previousAccountId && $previousAccountId !== $accountId) {
            $meta['previous_account'] = $this->accountMeta($previousAccountId);
        }

        return $meta;
    }

    private function accountMeta(int $accountId): array
    {
        $account = Account::withoutGlobalScopes()->findOrFail($accountId);

        return [
            'id' => $account->id,
            'balance' => $account->balance,
        ];
    }

    private function summary(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $incomeTotal = (clone $query)->income()->sum('amount');
        $outcomeTotal = (clone $query)->outcome()->sum('amount');

        $incomeTotal = round((float) $incomeTotal, 2);
        $outcomeTotal = round((float) $outcomeTotal, 2);

        return [
            'income_total' => $incomeTotal,
            'outcome_total' => $outcomeTotal,
            'balance' => round($incomeTotal - $outcomeTotal, 2),
        ];
    }
}
