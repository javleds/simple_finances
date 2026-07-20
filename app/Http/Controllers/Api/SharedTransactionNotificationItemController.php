<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SharedTransactionNotificationItemRequest;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedTransactionNotificationItemController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            SharedTransactionNotificationItem::query()
            ->whereHas('batch', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['batch', 'account', 'transaction', 'modifier'])
            ->latest(),
            $request,
            filterColumns: ['batch_id', 'account_id', 'transaction_id', 'modifier_id', 'action', 'type'],
        );
    }

    public function store(SharedTransactionNotificationItemRequest $request): JsonResponse
    {
        $batch = SharedTransactionNotificationBatch::query()->findOrFail($request->integer('batch_id'));
        abort_unless($batch->user_id === $request->user()->id, 403);
        $payload = $request->validated();
        $payload['account_id'] = $this->resolveAccountId($request, $batch);

        $record = SharedTransactionNotificationItem::create($payload);

        return $this->respondModel($record, ['batch', 'account', 'transaction', 'modifier'], 201);
    }

    public function show(SharedTransactionNotificationItem $item): JsonResponse
    {
        abort_unless($item->batch->user_id === auth()->id(), 403);

        return $this->respondModel($item, ['batch', 'account', 'transaction', 'modifier']);
    }

    public function update(
        SharedTransactionNotificationItemRequest $request,
        SharedTransactionNotificationItem $item,
    ): JsonResponse {
        abort_unless($item->batch->user_id === auth()->id(), 403);
        $payload = $request->validated();
        $payload['account_id'] = $this->resolveAccountId($request, $item->batch);

        $item->update($payload);

        return $this->respondModel($item->fresh(), ['batch', 'account', 'transaction', 'modifier']);
    }

    public function delete(SharedTransactionNotificationItem $item): JsonResponse
    {
        abort_unless($item->batch->user_id === auth()->id(), 403);

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
