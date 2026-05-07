<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\NotificationSettingsUpdateRequest;
use App\Models\Account;
use App\Services\NotificableAccountSetupBuilder;
use App\Services\NotificationSetupBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends ApiController
{
    public function show(
        Request $request,
        NotificationSetupBuilder $notificationSetupBuilder,
        NotificableAccountSetupBuilder $notificableAccountSetupBuilder,
    ): JsonResponse {
        $user = $request->user();

        return $this->respond([
            'data' => [
                'notification_types' => $notificationSetupBuilder->handle($user),
                'accounts' => $notificableAccountSetupBuilder->handle($user),
            ],
        ]);
    }

    public function update(NotificationSettingsUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $accountIds = Account::query()
            ->whereIn('id', $request->input('account_ids', []))
            ->pluck('id')
            ->all();

        $user->notificationTypes()->sync($request->input('notification_type_ids', []));
        $user->notificableAccounts()->sync($accountIds);

        return $this->respond([
            'message' => 'Notification settings updated successfully.',
        ]);
    }
}
