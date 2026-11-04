<?php

namespace App\Http\Controllers\Api;

use FinSi\FinanceDomain\Queries\UserOwnedResources;
use App\Http\Requests\Api\FixedIncomeRequest;
use FinSi\FinanceDomain\Models\FixedIncome;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedIncomeController extends ApiController
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->userOwnedResources->constrain(FixedIncome::query(), (int) $request->user()->id)
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
        $this->authorizeUserOwnedResource->ensureOwned($fixedIncome, (int) auth()->id());

        return $this->respondModel($fixedIncome, ['partials', 'outcomes']);
    }

    public function update(FixedIncomeRequest $request, FixedIncome $fixedIncome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($fixedIncome, $request->user()->id);

        $fixedIncome->update($request->validated());

        return $this->respondModel($fixedIncome->fresh(), ['partials', 'outcomes']);
    }

    public function delete(FixedIncome $fixedIncome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($fixedIncome, (int) auth()->id());

        $fixedIncome->delete();

        return $this->respondDeleted('Fixed income deleted successfully.');
    }
}
