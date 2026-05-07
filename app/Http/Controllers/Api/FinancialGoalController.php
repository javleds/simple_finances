<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FinancialGoalRequest;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialGoalController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            FinancialGoal::query()
            ->with('account')
            ->latest(),
            $request,
        );
    }

    public function store(FinancialGoalRequest $request): JsonResponse
    {
        $goal = FinancialGoal::create($request->validated());

        return $this->respondModel($goal, ['account'], 201);
    }

    public function show(FinancialGoal $financialGoal): JsonResponse
    {
        return $this->respondModel($financialGoal, ['account']);
    }

    public function update(FinancialGoalRequest $request, FinancialGoal $financialGoal): JsonResponse
    {
        $financialGoal->update($request->validated());

        return $this->respondModel($financialGoal->fresh(), ['account']);
    }

    public function delete(FinancialGoal $financialGoal): JsonResponse
    {
        Transaction::query()
            ->where('financial_goal_id', $financialGoal->id)
            ->update(['financial_goal_id' => null]);

        $financialGoal->delete();

        return $this->respond([
            'message' => 'Financial goal deleted successfully.',
        ]);
    }
}
