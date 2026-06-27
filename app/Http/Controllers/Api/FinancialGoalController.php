<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FinancialGoalRequest;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\Api\AuthorizeUserOwnedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialGoalController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            FinancialGoal::query()
            ->with('account')
            ->where('user_id', $request->user()->id)
            ->latest(),
            $request,
            filterColumns: ['account_id', 'status'],
        );
    }

    public function store(FinancialGoalRequest $request): JsonResponse
    {
        $account = Account::withoutGlobalScopes()->findOrFail($request->integer('account_id'));
        $this->authorizeAccountAccess->ensureMember($account, $request->user()->id);

        $goal = FinancialGoal::create($request->validated());

        return $this->respondModel($goal, ['account'], 201);
    }

    public function show(FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($financialGoal);

        return $this->respondModel($financialGoal, ['account']);
    }

    public function update(FinancialGoalRequest $request, FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($financialGoal, $request->user()->id);
        $account = Account::withoutGlobalScopes()->findOrFail($request->integer('account_id'));
        $this->authorizeAccountAccess->ensureMember($account, $request->user()->id);

        $financialGoal->update($request->validated());

        return $this->respondModel($financialGoal->fresh(), ['account']);
    }

    public function delete(FinancialGoal $financialGoal): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($financialGoal);

        Transaction::query()
            ->where('financial_goal_id', $financialGoal->id)
            ->update(['financial_goal_id' => null]);

        $financialGoal->delete();

        return $this->respondDeleted('Financial goal deleted successfully.');
    }
}
