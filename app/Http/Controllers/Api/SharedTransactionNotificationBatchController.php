<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SharedTransactionNotificationBatchRequest;
use App\Models\SharedTransactionNotificationBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedTransactionNotificationBatchController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            SharedTransactionNotificationBatch::query()
            ->with(['account', 'items'])
            ->where('user_id', auth()->id())
            ->latest(),
            $request,
            filterColumns: ['account_id', 'status'],
        );
    }

    public function store(SharedTransactionNotificationBatchRequest $request): JsonResponse
    {
        $record = SharedTransactionNotificationBatch::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->respondModel($record, ['account', 'items'], 201);
    }

    public function show(SharedTransactionNotificationBatch $batch): JsonResponse
    {
        abort_unless($batch->user_id === auth()->id(), 403);

        return $this->respondModel($batch, ['account', 'items']);
    }

    public function update(
        SharedTransactionNotificationBatchRequest $request,
        SharedTransactionNotificationBatch $batch,
    ): JsonResponse {
        abort_unless($batch->user_id === auth()->id(), 403);

        $batch->update($request->validated());

        return $this->respondModel($batch->fresh(), ['account', 'items']);
    }

    public function delete(SharedTransactionNotificationBatch $batch): JsonResponse
    {
        abort_unless($batch->user_id === auth()->id(), 403);

        $batch->delete();

        return $this->respondDeleted('Shared transaction notification batch deleted successfully.');
    }
}
