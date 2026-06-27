<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\TelegramVerificationCodeRequest;
use App\Models\TelegramVerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramVerificationCodeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            TelegramVerificationCode::query()
            ->where('user_id', auth()->id())
            ->latest(),
            $request,
            filterColumns: ['used_at'],
        );
    }

    public function store(TelegramVerificationCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTelegramLinked()) {
            return $this->respond([
                'message' => 'Telegram is already linked.',
            ], 422);
        }

        $recentCount = $user->telegramVerificationCodes()
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= 3) {
            return $this->respond([
                'message' => 'You can only generate three verification codes per hour.',
            ], 422);
        }

        $record = TelegramVerificationCode::createForUser($user);

        return $this->respondModel($record, [], 201);
    }

    public function show(TelegramVerificationCode $telegramVerificationCode): JsonResponse
    {
        abort_unless($telegramVerificationCode->user_id === auth()->id(), 403);

        return $this->respondModel($telegramVerificationCode);
    }

    public function update(
        TelegramVerificationCodeRequest $request,
        TelegramVerificationCode $telegramVerificationCode,
    ): JsonResponse {
        abort_unless($telegramVerificationCode->user_id === auth()->id(), 403);

        if ($request->filled('used_at')) {
            $telegramVerificationCode->markAsUsed();
        }

        return $this->respondModel($telegramVerificationCode->fresh());
    }

    public function delete(TelegramVerificationCode $telegramVerificationCode): JsonResponse
    {
        abort_unless($telegramVerificationCode->user_id === auth()->id(), 403);

        $telegramVerificationCode->delete();

        return $this->respondDeleted('Telegram verification code deleted successfully.');
    }
}
