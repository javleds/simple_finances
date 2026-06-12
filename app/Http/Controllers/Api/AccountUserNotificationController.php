<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AccountUserNotificationRequest;
use App\Models\AccountUserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountUserNotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            AccountUserNotification::query()
            ->where('user_id', auth()->id())
            ->latest(),
            $request,
        );
    }

    public function store(AccountUserNotificationRequest $request): JsonResponse
    {
        $record = AccountUserNotification::create([
            'user_id' => $request->user()->id,
            'account_id' => $request->integer('account_id'),
        ]);

        return $this->respondModel($record, [], 201);
    }

    public function show(AccountUserNotification $accountUserNotification): JsonResponse
    {
        abort_unless($accountUserNotification->user_id === auth()->id(), 403);

        return $this->respondModel($accountUserNotification);
    }

    public function update(AccountUserNotificationRequest $request, AccountUserNotification $accountUserNotification): JsonResponse
    {
        abort_unless($accountUserNotification->user_id === auth()->id(), 403);

        $accountUserNotification->update([
            'account_id' => $request->integer('account_id'),
        ]);

        return $this->respondModel($accountUserNotification->fresh());
    }

    public function delete(AccountUserNotification $accountUserNotification): JsonResponse
    {
        abort_unless($accountUserNotification->user_id === auth()->id(), 403);

        $accountUserNotification->delete();

        return $this->respondDeleted('Account notification deleted successfully.');
    }
}
