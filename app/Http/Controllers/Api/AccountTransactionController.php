<?php

namespace App\Http\Controllers\Api;

use App\Dto\TransactionFormDto;
use App\Http\Requests\Api\AccountTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTransactionController extends ApiController
{
    public function index(Account $account, Request $request): JsonResponse
    {
        $this->ensureAccountMember($account);

        $query = $account->transactions()
            ->with(['account', 'user', 'financialGoal', 'subTransactions'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
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

        return $this->respondModel($transaction->fresh(), ['account', 'user', 'financialGoal', 'subTransactions'], 201);
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

        $payload = $request->validated();
        $payload['id'] = $transaction->id;
        $payload['account_id'] = $account->id;

        $transaction = $transactionUpdater->execute(
            $transaction,
            TransactionFormDto::fromFormArray($payload)
        );

        return $this->respondModel($transaction->fresh(), ['account', 'user', 'financialGoal', 'subTransactions']);
    }

    public function delete(Account $account, Transaction $transaction, TransactionRemover $transactionRemover): JsonResponse
    {
        $this->ensureAccountTransaction($account, $transaction);
        abort_unless($transaction->user_id === auth()->id(), 403);

        $transactionRemover->execute($transaction);

        return $this->respond([
            'message' => 'Account transaction deleted successfully.',
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
}
