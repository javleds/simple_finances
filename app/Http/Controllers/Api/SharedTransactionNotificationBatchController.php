<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SharedTransactionNotificationBatchRequest;
use App\Models\Account;
use App\Models\SharedTransactionNotificationBatch;
use App\Services\Api\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedTransactionNotificationBatchController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
    ) {}

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
        $this->ensureAccountAccess($request);

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
        $this->ensureAccountAccess($request);

        $batch->update($request->validated());

        return $this->respondModel($batch->fresh(), ['account', 'items']);
    }

    public function delete(SharedTransactionNotificationBatch $batch): JsonResponse
    {
        abort_unless($batch->user_id === auth()->id(), 403);

        $batch->delete();

        return $this->respondDeleted('Shared transaction notification batch deleted successfully.');
    }

    private function ensureAccountAccess(SharedTransactionNotificationBatchRequest $request): void
    {
        $account = Account::withoutGlobalScopes()->findOrFail($request->integer('account_id'));
        $this->authorizeAccountAccess->ensureMember($account, $request->user()->id);
    }
}
