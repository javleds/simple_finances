<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AccountFinancialGoalRequest;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountFinancialGoalController extends ApiController
{
    public function index(Account $account, Request $request): JsonResponse
    {
        $this->ensureAccountMember($account);

        $query = $account->financialGoals()
            ->with('account')
            ->latest()
            ->getQuery();

        return $this->respondPaginated(
            $query,
            $request,
        );
    }

    public function store(Account $account, AccountFinancialGoalRequest $request): JsonResponse
    {
        $this->ensureAccountMember($account);

        $goal = $account->financialGoals()->create($request->validated());

        return $this->respondModel($goal, ['account'], 201);
    }

    public function show(Account $account, FinancialGoal $financialGoal): JsonResponse
    {
        $this->ensureAccountGoal($account, $financialGoal);

        return $this->respondModel($financialGoal, ['account']);
    }

    public function update(
        Account $account,
        FinancialGoal $financialGoal,
        AccountFinancialGoalRequest $request,
    ): JsonResponse {
        $this->ensureAccountGoal($account, $financialGoal);

        $financialGoal->update($request->validated());

        return $this->respondModel($financialGoal->fresh(), ['account']);
    }

    public function delete(Account $account, FinancialGoal $financialGoal): JsonResponse
    {
        $this->ensureAccountGoal($account, $financialGoal);

        Transaction::query()
            ->where('financial_goal_id', $financialGoal->id)
            ->update(['financial_goal_id' => null]);

        $financialGoal->delete();

        return $this->respond([
            'message' => 'Account financial goal deleted successfully.',
        ]);
    }

    private function ensureAccountMember(Account $account): void
    {
        abort_unless($account->users()->where('users.id', auth()->id())->exists(), 403);
    }

    private function ensureAccountGoal(Account $account, FinancialGoal $financialGoal): void
    {
        $this->ensureAccountMember($account);
        abort_unless($financialGoal->account_id === $account->id, 404);
    }
}
