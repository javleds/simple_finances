<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FixedOutcomeRequest;
use App\Models\FixedIncome;
use App\Models\FixedOutcome;
use App\Services\Api\AuthorizeUserOwnedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedOutcomeController extends ApiController
{
    public function __construct(private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            FixedOutcome::query()
            ->with('fixedIncome')
            ->where('user_id', $request->user()->id)
            ->latest(),
            $request,
            searchColumns: ['name'],
            filterColumns: ['fixed_income_id', 'type'],
        );
    }

    public function store(FixedOutcomeRequest $request): JsonResponse
    {
        $fixedIncome = FixedIncome::withoutGlobalScopes()->findOrFail($request->integer('fixed_income_id'));
        $this->authorizeUserOwnedResource->ensureOwned($fixedIncome, $request->user()->id);

        $record = FixedOutcome::create($request->validated());

        return $this->respondModel($record, ['fixedIncome'], 201);
    }

    public function show(FixedOutcome $fixedOutcome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($fixedOutcome);

        return $this->respondModel($fixedOutcome, ['fixedIncome']);
    }

    public function update(FixedOutcomeRequest $request, FixedOutcome $fixedOutcome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($fixedOutcome, $request->user()->id);
        $fixedIncome = FixedIncome::withoutGlobalScopes()->findOrFail($request->integer('fixed_income_id'));
        $this->authorizeUserOwnedResource->ensureOwned($fixedIncome, $request->user()->id);

        $fixedOutcome->update($request->validated());

        return $this->respondModel($fixedOutcome->fresh(), ['fixedIncome']);
    }

    public function delete(FixedOutcome $fixedOutcome): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($fixedOutcome);

        $fixedOutcome->delete();

        return $this->respondDeleted('Fixed outcome deleted successfully.');
    }
}
