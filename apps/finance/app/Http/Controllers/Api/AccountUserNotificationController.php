<?php

namespace App\Http\Controllers\Api;

use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Queries\UserOwnedResources;
use App\Http\Requests\Api\AccountUserNotificationRequest;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountUserNotification;
use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountUserNotificationController extends ApiController
{
    public function __construct(
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
        private readonly UserOwnedResources $userOwnedResources,
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->userOwnedResources->constrain(AccountUserNotification::query(), (int) auth()->id())
            ->latest(),
            $request,
            filterColumns: ['account_id'],
        );
    }

    public function store(AccountUserNotificationRequest $request): JsonResponse
    {
        $this->ensureAccountAccess($request);

        $record = AccountUserNotification::create([
            'user_id' => $request->user()->id,
            'account_id' => $request->integer('account_id'),
        ]);

        return $this->respondModel($record, [], 201);
    }

    public function show(AccountUserNotification $accountUserNotification): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($accountUserNotification, (int) auth()->id());

        return $this->respondModel($accountUserNotification);
    }

    public function update(AccountUserNotificationRequest $request, AccountUserNotification $accountUserNotification): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($accountUserNotification, (int) auth()->id());
        $this->ensureAccountAccess($request);

        $accountUserNotification->update([
            'account_id' => $request->integer('account_id'),
        ]);

        return $this->respondModel($accountUserNotification->fresh());
    }

    public function delete(AccountUserNotification $accountUserNotification): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($accountUserNotification, (int) auth()->id());

        $accountUserNotification->delete();

        return $this->respondDeleted('Account notification deleted successfully.');
    }

    private function ensureAccountAccess(AccountUserNotificationRequest $request): void
    {
        $account = Account::withoutGlobalScopes()->findOrFail($request->integer('account_id'));
        $this->authorizeAccountAccess->ensureMember($account, $request->user()->id);
    }
}
