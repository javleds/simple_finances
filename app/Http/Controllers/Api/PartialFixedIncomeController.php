<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PartialFixedIncomeRequest;
use App\Models\FixedIncome;
use App\Models\PartialFixedIncome;
use App\Services\Api\AuthorizeUserOwnedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartialFixedIncomeController extends ApiController
{
    public function __construct(private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            PartialFixedIncome::query()
            ->with('fixedIncome')
            ->where('user_id', $request->user()->id)
            ->latest(),
            $request,
            filterColumns: ['fixed_income_id'],
        );
    }

    public function store(PartialFixedIncomeRequest $request): JsonResponse
    {
        $fixedIncome = FixedIncome::withoutGlobalScopes()->findOrFail($request->integer('fixed_income_id'));
        $this->authorizeUserOwnedResource->ensureOwned($fixedIncome, $request->user()->id);

        $record = PartialFixedIncome::create($request->validated());

        return $this->respondModel($record, ['fixedIncome'], 201);
    }

    public function show(PartialFixedIncome $partialFixedIncome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($partialFixedIncome);

        return $this->respondModel($partialFixedIncome, ['fixedIncome']);
    }

    public function update(PartialFixedIncomeRequest $request, PartialFixedIncome $partialFixedIncome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($partialFixedIncome, $request->user()->id);
        $fixedIncome = FixedIncome::withoutGlobalScopes()->findOrFail($request->integer('fixed_income_id'));
        $this->authorizeUserOwnedResource->ensureOwned($fixedIncome, $request->user()->id);

        $partialFixedIncome->update($request->validated());

        return $this->respondModel($partialFixedIncome->fresh(), ['fixedIncome']);
    }

    public function delete(PartialFixedIncome $partialFixedIncome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($partialFixedIncome);

        $partialFixedIncome->delete();

        return $this->respondDeleted('Partial fixed income deleted successfully.');
    }
}
