<?php

namespace App\Http\Controllers\Api;

use FinSi\FinanceDomain\Queries\UserOwnedResources;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use App\Http\Requests\Api\SharedTransactionNotificationBatchRequest;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\SharedTransactionNotificationBatch;
use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedTransactionNotificationBatchController extends ApiController
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->userOwnedResources->constrain(SharedTransactionNotificationBatch::query(), (int) auth()->id())
            ->with(['account', 'items'])
            ->latest(),
            $request,
            filterColumns: ['account_id', 'group_key', 'status'],
        );
    }

    public function store(SharedTransactionNotificationBatchRequest $request): JsonResponse
    {
        $this->ensureAccountAccess($request);

        $record = SharedTransactionNotificationBatch::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->respondModel($record, ['account', 'items'], 201);
    }

    public function show(SharedTransactionNotificationBatch $batch): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($batch, (int) auth()->id());

        return $this->respondModel($batch, ['account', 'items']);
    }

    public function update(
        SharedTransactionNotificationBatchRequest $request,
        SharedTransactionNotificationBatch $batch,
    ): JsonResponse {
        $this->authorizeUserOwnedResource->ensureOwned($batch, (int) auth()->id());
        $this->ensureAccountAccess($request);

        $batch->update($request->validated());

        return $this->respondModel($batch->fresh(), ['account', 'items']);
    }

    public function delete(SharedTransactionNotificationBatch $batch): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($batch, (int) auth()->id());

        $batch->delete();

        return $this->respondDeleted('Shared transaction notification batch deleted successfully.');
    }

    private function ensureAccountAccess(SharedTransactionNotificationBatchRequest $request): void
    {
        $account = Account::withoutGlobalScopes()->findOrFail($request->integer('account_id'));
        $this->authorizeAccountAccess->ensureMember($account, $request->user()->id);
    }
}
