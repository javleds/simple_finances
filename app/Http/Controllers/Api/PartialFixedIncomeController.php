<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PartialFixedIncomeRequest;
use App\Models\FixedIncome;
use App\Models\PartialFixedIncome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartialFixedIncomeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            PartialFixedIncome::query()
            ->with('fixedIncome')
            ->latest(),
            $request,
            filterColumns: ['fixed_income_id'],
        );
    }

    public function store(PartialFixedIncomeRequest $request): JsonResponse
    {
        FixedIncome::query()->findOrFail($request->integer('fixed_income_id'));

        $record = PartialFixedIncome::create($request->validated());

        return $this->respondModel($record, ['fixedIncome'], 201);
    }

    public function show(PartialFixedIncome $partialFixedIncome): JsonResponse
    {
        return $this->respondModel($partialFixedIncome, ['fixedIncome']);
    }

    public function update(PartialFixedIncomeRequest $request, PartialFixedIncome $partialFixedIncome): JsonResponse
    {
        $partialFixedIncome->update($request->validated());

        return $this->respondModel($partialFixedIncome->fresh(), ['fixedIncome']);
    }

    public function delete(PartialFixedIncome $partialFixedIncome): JsonResponse
    {
        $partialFixedIncome->delete();

        return $this->respondDeleted('Partial fixed income deleted successfully.');
    }
}
