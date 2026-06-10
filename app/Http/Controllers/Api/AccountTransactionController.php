<?php

namespace App\Http\Controllers\Api;

use App\Dto\TransactionFormDto;
use App\Http\Requests\Api\AccountTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Accounts\BuildPendingIncomeByUser;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTransactionController extends ApiController
{
    public function __construct(
        private readonly BuildPendingIncomeByUser $buildPendingIncomeByUser,
    ) {}

    public function index(Account $account, Request $request): JsonResponse
    {
        $this->ensureAccountMember($account);

        $query = $account->transactions()
            ->with(['account', 'user', 'financialGoal', 'subTransactions'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->getQuery();

        return $this->respondPaginated(
            $query,
            $request,
        );
    }

    public function store(
        Account $account,
        AccountTransactionRequest $request,
        TransactionCreator $transactionCreator,
    ): JsonResponse {
        $this->ensureAccountMember($account);

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
            ['account', 'user', 'financialGoal', 'subTransactions'],
            201,
            $this->transactionAccountMeta($account->id),
        );
    }

    public function show(Account $account, Transaction $transaction): JsonResponse
    {
        $this->ensureAccountTransaction($account, $transaction);

        return $this->respondModel($transaction, ['account', 'user', 'financialGoal', 'subTransactions']);
    }

    public function update(
        Account $account,
        Transaction $transaction,
        AccountTransactionRequest $request,
        TransactionUpdater $transactionUpdater,
    ): JsonResponse {
        $this->ensureAccountTransaction($account, $transaction);
        abort_unless($transaction->user_id === $request->user()->id, 403);

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
            ['account', 'user', 'financialGoal', 'subTransactions'],
            meta: $this->transactionAccountMeta($transaction->account_id, $previousAccountId),
        );
    }

    public function delete(Account $account, Transaction $transaction, TransactionRemover $transactionRemover): JsonResponse
    {
        $this->ensureAccountTransaction($account, $transaction);
        abort_unless($transaction->user_id === auth()->id(), 403);

        $accountId = $transaction->account_id;
        $transactionRemover->execute($transaction);

        return $this->respond([
            'message' => 'Account transaction deleted successfully.',
            'meta' => $this->transactionAccountMeta($accountId),
        ]);
    }

    private function ensureAccountMember(Account $account): void
    {
        abort_unless($account->users()->where('users.id', auth()->id())->exists(), 403);
    }

    private function ensureAccountTransaction(Account $account, Transaction $transaction): void
    {
        $this->ensureAccountMember($account);
        abort_unless($transaction->account_id === $account->id, 404);
    }

    private function transactionAccountMeta(int $accountId, ?int $previousAccountId = null): array
    {
        $meta = [
            'account' => $this->accountMeta($accountId),
            'pending_by_user' => $this->pendingByUserMeta($accountId),
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

    private function pendingByUserMeta(int $accountId): array
    {
        $account = Account::withoutGlobalScopes()->findOrFail($accountId);

        return $this->buildPendingIncomeByUser->execute($account);
    }

    private function createdTransactions(Transaction $transaction): ?EloquentCollection
    {
        if (! $transaction->subTransactions()->exists()) {
            return null;
        }

        return Transaction::query()
            ->with(['account', 'user', 'financialGoal'])
            ->where('id', $transaction->id)
            ->orWhere('parent_transaction_id', $transaction->id)
            ->orderByRaw('case when id = ? then 0 else 1 end', [$transaction->id])
            ->orderBy('id')
            ->get();
    }
}
