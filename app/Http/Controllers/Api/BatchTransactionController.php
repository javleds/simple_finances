<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Dashboard\BatchTransactionsRequest;
use App\Services\Dashboard\CompletePendingTransactionsBatch;
use Illuminate\Http\JsonResponse;

class BatchTransactionController extends ApiController
{
    public function store(
        BatchTransactionsRequest $request,
        CompletePendingTransactionsBatch $completePendingTransactionsBatch,
    ): JsonResponse {
        return $this->respond([
            'data' => $completePendingTransactionsBatch->execute($request->validated('transaction_ids')),
        ]);
    }
}
