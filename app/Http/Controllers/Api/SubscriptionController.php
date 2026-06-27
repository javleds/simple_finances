<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SubscriptionRequest;
use App\Models\Account;
use App\Models\Subscription;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\Api\AuthorizeUserOwnedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            Subscription::query()
            ->with(['feedAccount', 'payments'])
            ->where('user_id', $request->user()->id)
            ->orderBy('next_payment_date'),
            $request,
            ['finished' => 'finished_at'],
            searchColumns: ['name'],
            filterColumns: ['feed_account_id', 'frequency', 'period'],
        );
    }

    public function store(SubscriptionRequest $request): JsonResponse
    {
        $this->ensureFeedAccountAccess($request);

        $record = Subscription::create($request->validated());

        return $this->respondModel($record, ['feedAccount', 'payments'], 201);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscription);

        return $this->respondModel($subscription, ['feedAccount', 'payments']);
    }

    public function update(SubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscription, $request->user()->id);
        $this->ensureFeedAccountAccess($request);

        $subscription->update($request->validated());

        return $this->respondModel($subscription->fresh(), ['feedAccount', 'payments']);
    }

    public function delete(Subscription $subscription): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscription);

        $subscription->delete();

        return $this->respondDeleted('Subscription deleted successfully.');
    }

    private function ensureFeedAccountAccess(SubscriptionRequest $request): void
    {
        $feedAccountId = $request->integer('feed_account_id');

        if ($feedAccountId === 0) {
            return;
        }

        $account = Account::withoutGlobalScopes()->findOrFail($feedAccountId);
        $this->authorizeAccountAccess->ensureMember($account, $request->user()->id);
    }
}
