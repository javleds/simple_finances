<?php

namespace App\Http\Controllers\Api;

use App\Dto\TransactionFormDto;
use App\Dto\ApiIndexCriteriaDto;
use App\Http\Requests\Api\AccountTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\Api\ModelIndexCriteria;
use App\Services\Transaction\BuildTransactionAccountMeta;
use App\Services\Transaction\HydrateCurrentUserPendingReimbursements;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTransactionController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildTransactionAccountMeta $buildTransactionAccountMeta,
    ) {}

    public function index(
        Account $account,
        Request $request,
        HydrateCurrentUserPendingReimbursements $hydrateCurrentUserPendingReimbursements,
        ModelIndexCriteria $modelIndexCriteria,
    ): JsonResponse
    {
        $this->ensureAccountMember($account);

        $query = $account->transactions()
            ->with(['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'subTransactions', 'allocations.user'])
            ->whereNull('legacy_migrated_at')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->getQuery();

        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $paginator = $modelIndexCriteria
            ->apply($query, new ApiIndexCriteriaDto(
                request: $request,
                filterColumns: ['type', 'status', 'financial_goal_id', 'user_id', 'parent_transaction_id'],
                searchColumns: ['concept'],
            ))
            ->paginate($perPage)
            ->withQueryString();

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
            ],
        ]);
    }

    public function store(
        Account $account,
        AccountTransactionRequest $request,
        TransactionCreator $transactionCreator,
    ): JsonResponse {
        $this->ensureAccountMember($account);
        $this->ensurePayloadUsersBelongToAccount($account, $request);

        $payload = $request->validated();
        $payload['account_id'] = $account->id;

        $transaction = $transactionCreator->execute(
            TransactionFormDto::fromFormArray($payload)
        );

        $createdTransactions = $this->createdTransactions($transaction);

        if ($createdTransactions !== null) {
            return $this->respond([
                'data' => $createdTransactions,
                'meta' => $this->transactionAccountMeta($account->id),
            ], 201);
        }

        return $this->respondModel(
            $transaction->fresh(),
            ['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'subTransactions', 'allocations.user'],
            201,
            $this->transactionAccountMeta($account->id),
        );
    }

    public function show(Account $account, Transaction $transaction): JsonResponse
    {
        $this->ensureAccountTransaction($account, $transaction);

        return $this->respondModel($transaction, ['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'subTransactions', 'allocations.user']);
    }

    public function update(
        Account $account,
        Transaction $transaction,
        AccountTransactionRequest $request,
        TransactionUpdater $transactionUpdater,
    ): JsonResponse {
        $this->ensureAccountTransaction($account, $transaction);
        abort_unless($transaction->user_id === $request->user()->id, 403);
        abort_if($transaction->account_balance_snapshot_id !== null, 422, 'Snapshot adjustment transactions cannot be edited directly.');
        $this->ensurePayloadUsersBelongToAccount($account, $request);

        $previousAccountId = $transaction->account_id;
        $payload = $request->validated();
        $payload['id'] = $transaction->id;
        $payload['account_id'] = $account->id;

        $transaction = $transactionUpdater->execute(
            $transaction,
            TransactionFormDto::fromFormArray($payload)
        );

        return $this->respondModel(
            $transaction->fresh(),
            ['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'subTransactions', 'allocations.user'],
            meta: $this->transactionAccountMeta($transaction->account_id, $previousAccountId),
        );
    }

    public function delete(Account $account, Transaction $transaction, TransactionRemover $transactionRemover): JsonResponse
    {
        $this->ensureAccountTransaction($account, $transaction);
        abort_unless($transaction->user_id === auth()->id(), 403);
        abort_if($transaction->account_balance_snapshot_id !== null, 422, 'Snapshot adjustment transactions cannot be deleted directly.');

        $accountId = $transaction->account_id;
        $subTransactionIds = $transactionRemover->execute($transaction);
        $meta = $this->transactionAccountMeta($accountId);
        $meta['subtransactions'] = $subTransactionIds;

        return $this->respondDeleted(
            'Account transaction deleted successfully.',
            $meta,
        );
    }

    private function ensureAccountMember(Account $account): void
    {
        $this->authorizeAccountAccess->ensureMember($account);
    }

    private function ensureAccountTransaction(Account $account, Transaction $transaction): void
    {
        $this->ensureAccountMember($account);
        $this->authorizeAccountAccess->ensureBelongsToAccount($account, $transaction->account_id);
    }

    private function ensurePayloadUsersBelongToAccount(Account $account, AccountTransactionRequest $request): void
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

    private function transactionAccountMeta(int $accountId, ?int $previousAccountId = null): array
    {
        return $this->buildTransactionAccountMeta->execute(
            accountId: $accountId,
            previousAccountId: $previousAccountId,
        );
    }

    private function createdTransactions(Transaction $transaction): ?EloquentCollection
    {
        if (! $transaction->subTransactions()->whereNull('legacy_migrated_at')->exists()) {
            return null;
        }

        return Transaction::query()
            ->with(['account', 'user', 'paidByUser', 'custodianUser', 'financialGoal', 'allocations.user'])
            ->where('id', $transaction->id)
            ->orWhere(function ($query) use ($transaction): void {
                $query
                    ->where('parent_transaction_id', $transaction->id)
                    ->whereNull('legacy_migrated_at');
            })
            ->orderByRaw('case when id = ? then 0 else 1 end', [$transaction->id])
            ->orderBy('id')
            ->get();
    }
}
