<?php

namespace App\Http\Controllers\Api;

use FinSi\FinanceDomain\Queries\UserOwnedResources;
use App\Http\Requests\Api\SubscriptionRequest;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->userOwnedResources->constrain(Subscription::query(), (int) $request->user()->id)
            ->with(['feedAccount', 'payments'])
            ->orderBy('next_payment_date'),
            $request,
            ['finished' => 'finished_at'],
            searchColumns: ['name'],
            filterColumns: ['feed_account_id', 'frequency_type', 'frequency_unit'],
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
        $this->authorizeUserOwnedResource->ensureOwned($subscription, (int) auth()->id());

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
        $this->authorizeUserOwnedResource->ensureOwned($subscription, (int) auth()->id());

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
