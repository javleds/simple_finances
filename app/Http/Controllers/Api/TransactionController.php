<?php

namespace App\Http\Controllers\Api;

use App\Dto\TransactionFormDto;
use App\Http\Requests\Api\TransactionIndexRequest;
use App\Http\Requests\Api\TransactionRequest;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\Transaction\BuildTransactionAccountMeta;
use App\Services\Transaction\BuildTransactionFacilityQuery;
use App\Services\Transaction\BuildTransactionSummary;
use App\Services\Transaction\HydrateCurrentUserPendingReimbursements;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Http\JsonResponse;

class TransactionController extends ApiController
{
    public function __construct(private readonly AuthorizeAccountAccess $authorizeAccountAccess) {}

    public function index(
        TransactionIndexRequest $request,
        BuildTransactionFacilityQuery $buildTransactionFacilityQuery,
        BuildTransactionSummary $buildTransactionSummary,
        HydrateCurrentUserPendingReimbursements $hydrateCurrentUserPendingReimbursements,
    ): JsonResponse
    {
        $query = $buildTransactionFacilityQuery->execute(
            $request->validated(),
            $request->user()->id,
        );
        $summary = $buildTransactionSummary->execute($query);
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $paginator = $query->paginate($perPage)->withQueryString();

        $hydrateCurrentUserPendingReimbursements->execute($paginator->items(), $request->user()->id);

        return $this->respond([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'summary' => $summary->toArray(),
            ],
        ]);
    }

    public function store(
        TransactionRequest $request,
        TransactionCreator $transactionCreator,
        BuildTransactionAccountMeta $buildTransactionAccountMeta,
    ): JsonResponse {
        $this->ensureTransactionPayloadAccess($request);

        $transaction = $transactionCreator->execute(
            TransactionFormDto::fromFormArray($request->validated())
        );

        return $this->respondModel(
            $transaction->fresh(),
            ['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'subTransactions', 'allocations.user'],
            201,
            $buildTransactionAccountMeta->execute($transaction->account_id),
        );
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $this->ensureVisibleTransaction($transaction);

        return $this->respondModel($transaction, ['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'subTransactions', 'allocations.user']);
    }

    public function update(
        TransactionRequest $request,
        Transaction $transaction,
        TransactionUpdater $transactionUpdater,
        BuildTransactionAccountMeta $buildTransactionAccountMeta,
    ): JsonResponse {
        abort_unless($transaction->user_id === $request->user()->id, 403);
        abort_if($transaction->account_balance_snapshot_id !== null, 422, 'Snapshot adjustment transactions cannot be edited directly.');
        $this->ensureTransactionPayloadAccess($request);

        $previousAccountId = $transaction->account_id;
        $payload = $request->validated();
        $payload['id'] = $transaction->id;

        $transaction = $transactionUpdater->execute(
            $transaction,
            TransactionFormDto::fromFormArray($payload)
        );

        return $this->respondModel(
            $transaction->fresh(),
            ['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'subTransactions', 'allocations.user'],
            meta: $buildTransactionAccountMeta->execute($transaction->account_id, $previousAccountId),
        );
    }

    public function delete(
        Transaction $transaction,
        TransactionRemover $transactionRemover,
        BuildTransactionAccountMeta $buildTransactionAccountMeta,
    ): JsonResponse {
        abort_unless($transaction->user_id === auth()->id(), 403);
        abort_if($transaction->account_balance_snapshot_id !== null, 422, 'Snapshot adjustment transactions cannot be deleted directly.');

        $accountId = $transaction->account_id;
        $subTransactionIds = $transactionRemover->execute($transaction);
        $meta = $buildTransactionAccountMeta->execute($accountId);
        $meta['subtransactions'] = $subTransactionIds;

        return $this->respondDeleted(
            'Transaction deleted successfully.',
            $meta,
        );
    }

    private function ensureVisibleTransaction(Transaction $transaction): void
    {
        $account = Account::withoutGlobalScopes()->findOrFail($transaction->account_id);
        $this->authorizeAccountAccess->ensureMember($account);
    }

    private function ensureTransactionPayloadAccess(TransactionRequest $request): void
    {
        $account = Account::withoutGlobalScopes()->findOrFail($request->integer('account_id'));
        $this->authorizeAccountAccess->ensureMember($account, $request->user()->id);
        $this->ensurePayloadUsersBelongToAccount($account, $request);

        $financialGoalId = $request->integer('financial_goal_id');

        if ($financialGoalId === 0) {
            return;
        }

        $financialGoal = FinancialGoal::query()->findOrFail($financialGoalId);
        $this->authorizeAccountAccess->ensureBelongsToAccount($account, $financialGoal->account_id);
    }

    private function ensurePayloadUsersBelongToAccount(Account $account, TransactionRequest $request): void
    {
        $userIds = collect([
            $request->integer('paid_by_user_id') ?: null,
            $request->integer('custodian_user_id') ?: null,
        ])
            ->merge(collect($request->input('user_payments', []))->pluck('user_id'))
            ->filter()
            ->unique();

        foreach ($userIds as $userId) {
            $this->authorizeAccountAccess->ensureAccountUser(
                $account,
                \App\Models\User::withoutGlobalScopes()->findOrFail((int) $userId),
            );
        }
    }
}
