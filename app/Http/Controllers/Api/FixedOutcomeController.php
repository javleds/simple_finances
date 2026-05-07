<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FixedOutcomeRequest;
use App\Models\FixedIncome;
use App\Models\FixedOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedOutcomeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            FixedOutcome::query()
            ->with('fixedIncome')
            ->latest(),
            $request,
        );
    }

    public function store(FixedOutcomeRequest $request): JsonResponse
    {
        FixedIncome::query()->findOrFail($request->integer('fixed_income_id'));

        $record = FixedOutcome::create($request->validated());

        return $this->respondModel($record, ['fixedIncome'], 201);
    }

    public function show(FixedOutcome $fixedOutcome): JsonResponse
    {
        return $this->respondModel($fixedOutcome, ['fixedIncome']);
    }

    public function update(FixedOutcomeRequest $request, FixedOutcome $fixedOutcome): JsonResponse
    {
        $fixedOutcome->update($request->validated());

        return $this->respondModel($fixedOutcome->fresh(), ['fixedIncome']);
    }

    public function delete(FixedOutcome $fixedOutcome): JsonResponse
    {
        $fixedOutcome->delete();

        return $this->respond([
            'message' => 'Fixed outcome deleted successfully.',
        ]);
    }
}
