<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FixedIncomeRequest;
use App\Models\FixedIncome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedIncomeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            FixedIncome::query()
            ->with(['partials', 'outcomes'])
            ->latest(),
            $request,
            searchColumns: ['name'],
            filterColumns: ['frequency'],
        );
    }

    public function store(FixedIncomeRequest $request): JsonResponse
    {
        $record = FixedIncome::create($request->validated());

        return $this->respondModel($record, ['partials', 'outcomes'], 201);
    }

    public function show(FixedIncome $fixedIncome): JsonResponse
    {
        return $this->respondModel($fixedIncome, ['partials', 'outcomes']);
    }

    public function update(FixedIncomeRequest $request, FixedIncome $fixedIncome): JsonResponse
    {
        $fixedIncome->update($request->validated());

        return $this->respondModel($fixedIncome->fresh(), ['partials', 'outcomes']);
    }

    public function delete(FixedIncome $fixedIncome): JsonResponse
    {
        $fixedIncome->delete();

        return $this->respondDeleted('Fixed income deleted successfully.');
    }
}
