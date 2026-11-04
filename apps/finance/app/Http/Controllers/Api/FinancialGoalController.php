<?php

namespace App\Http\Controllers\Api;

use FinSi\FinanceDomain\Queries\UserOwnedResources;
use App\Http\Requests\Api\FinancialGoalRequest;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\FinancialGoal;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialGoalController extends ApiController
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->userOwnedResources->constrain(FinancialGoal::query(), (int) $request->user()->id)
            ->with('account')
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
        $this->authorizeUserOwnedResource->ensureOwned($financialGoal, (int) auth()->id());

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
        $this->authorizeUserOwnedResource->ensureOwned($financialGoal, (int) auth()->id());

        Transaction::query()
            ->where('financial_goal_id', $financialGoal->id)
            ->update(['financial_goal_id' => null]);

        $financialGoal->delete();

        return $this->respondDeleted('Financial goal deleted successfully.');
    }
}
