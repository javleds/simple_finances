<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SubscriptionRequest;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            Subscription::query()
            ->with(['feedAccount', 'payments'])
            ->orderBy('next_payment_date'),
            $request,
            ['finished' => 'finished_at'],
            searchColumns: ['name'],
            filterColumns: ['feed_account_id', 'frequency', 'period'],
        );
    }

    public function store(SubscriptionRequest $request): JsonResponse
    {
        $record = Subscription::create($request->validated());

        return $this->respondModel($record, ['feedAccount', 'payments'], 201);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        return $this->respondModel($subscription, ['feedAccount', 'payments']);
    }

    public function update(SubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $subscription->update($request->validated());

        return $this->respondModel($subscription->fresh(), ['feedAccount', 'payments']);
    }

    public function delete(Subscription $subscription): JsonResponse
    {
        $subscription->delete();

        return $this->respondDeleted('Subscription deleted successfully.');
    }
}
