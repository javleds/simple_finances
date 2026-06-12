<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\NotificationTypeRequest;
use App\Models\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTypeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            NotificationType::query()->orderBy('name'),
            $request,
        );
    }

    public function store(NotificationTypeRequest $request): JsonResponse
    {
        $record = NotificationType::create($request->validated());

        return $this->respondModel($record, [], 201);
    }

    public function show(NotificationType $notificationType): JsonResponse
    {
        return $this->respondModel($notificationType);
    }

    public function update(NotificationTypeRequest $request, NotificationType $notificationType): JsonResponse
    {
        $notificationType->update($request->validated());

        return $this->respondModel($notificationType->fresh());
    }

    public function delete(NotificationType $notificationType): JsonResponse
    {
        $notificationType->delete();

        return $this->respondDeleted('Notification type deleted successfully.');
    }
}
