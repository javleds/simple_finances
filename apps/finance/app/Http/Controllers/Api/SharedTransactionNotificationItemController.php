<?php

namespace App\Http\Controllers\Api;

use FinSi\FinanceDomain\Queries\VisibleNotificationItemsForUser;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use App\Http\Requests\Api\SharedTransactionNotificationItemRequest;
use FinSi\FinanceDomain\Models\SharedTransactionNotificationBatch;
use FinSi\FinanceDomain\Models\SharedTransactionNotificationItem;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedTransactionNotificationItemController extends ApiController
{
    public function __construct(
        private readonly VisibleNotificationItemsForUser $visibleNotificationItems,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->visibleNotificationItems->query((int) $request->user()->id)
            ->with(['batch', 'account', 'transaction', 'modifier'])
            ->latest(),
            $request,
            filterColumns: ['batch_id', 'account_id', 'transaction_id', 'modifier_id', 'action', 'type'],
        );
    }

    public function store(SharedTransactionNotificationItemRequest $request): JsonResponse
    {
        $batch = SharedTransactionNotificationBatch::query()->findOrFail($request->integer('batch_id'));
        $this->authorizeUserOwnedResource->ensureOwned($batch, (int) $request->user()->id);
        $payload = $request->validated();
        $payload['account_id'] = $this->resolveAccountId($request, $batch);

        $record = SharedTransactionNotificationItem::create($payload);

        return $this->respondModel($record, ['batch', 'account', 'transaction', 'modifier'], 201);
    }

    public function show(SharedTransactionNotificationItem $item): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($item->batch, (int) auth()->id());

        return $this->respondModel($item, ['batch', 'account', 'transaction', 'modifier']);
    }

    public function update(
        SharedTransactionNotificationItemRequest $request,
        SharedTransactionNotificationItem $item,
    ): JsonResponse {
        $this->authorizeUserOwnedResource->ensureOwned($item->batch, (int) auth()->id());
        $payload = $request->validated();
        $payload['account_id'] = $this->resolveAccountId($request, $item->batch);

        $item->update($payload);

        return $this->respondModel($item->fresh(), ['batch', 'account', 'transaction', 'modifier']);
    }

    public function delete(SharedTransactionNotificationItem $item): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($item->batch, (int) auth()->id());

        $item->delete();

        return $this->respondDeleted('Shared transaction notification item deleted successfully.');
    }

    private function resolveAccountId(
        SharedTransactionNotificationItemRequest $request,
        SharedTransactionNotificationBatch $batch,
    ): int {
        $transactionId = $request->integer('transaction_id');

        if ($transactionId !== 0) {
            return Transaction::withoutGlobalScopes()->findOrFail($transactionId)->account_id;
        }

        return $request->integer('account_id') ?: $batch->account_id;
    }
}
