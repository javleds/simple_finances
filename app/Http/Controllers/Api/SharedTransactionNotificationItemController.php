<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SharedTransactionNotificationItemRequest;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedTransactionNotificationItemController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            SharedTransactionNotificationItem::query()
            ->whereHas('batch', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['batch', 'transaction', 'modifier'])
            ->latest(),
            $request,
            filterColumns: ['batch_id', 'transaction_id', 'modifier_id', 'action', 'type'],
        );
    }

    public function store(SharedTransactionNotificationItemRequest $request): JsonResponse
    {
        $batch = SharedTransactionNotificationBatch::query()->findOrFail($request->integer('batch_id'));
        abort_unless($batch->user_id === $request->user()->id, 403);

        $record = SharedTransactionNotificationItem::create($request->validated());

        return $this->respondModel($record, ['batch', 'transaction', 'modifier'], 201);
    }

    public function show(SharedTransactionNotificationItem $item): JsonResponse
    {
        abort_unless($item->batch->user_id === auth()->id(), 403);

        return $this->respondModel($item, ['batch', 'transaction', 'modifier']);
    }

    public function update(
        SharedTransactionNotificationItemRequest $request,
        SharedTransactionNotificationItem $item,
    ): JsonResponse {
        abort_unless($item->batch->user_id === auth()->id(), 403);

        $item->update($request->validated());

        return $this->respondModel($item->fresh(), ['batch', 'transaction', 'modifier']);
    }

    public function delete(SharedTransactionNotificationItem $item): JsonResponse
    {
        abort_unless($item->batch->user_id === auth()->id(), 403);

        $item->delete();

        return $this->respondDeleted('Shared transaction notification item deleted successfully.');
    }
}
