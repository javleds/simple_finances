<?php

namespace App\Http\Controllers\Api;

use App\Dto\TransactionFormDto;
use App\Http\Requests\Api\TransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            Transaction::query()
            ->with(['account', 'user', 'financialGoal', 'subTransactions'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at'),
            $request,
        );
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
}
