<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        return $this->respond([
            'data' => $request->user()->loadMissing('telegramVerificationCodes'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $payload = [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone_number' => $request->string('phone_number')->toString() ?: null,
        ];

        if ($request->filled('password')) {
            $payload['password'] = $request->string('password')->toString();
        }

        $user->update($payload);

        if ($user->wasChanged('email')) {
            $user->forceFill(['email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        }

        return $this->respond([
            'message' => 'Profile updated successfully.',
            'data' => $user->fresh(),
        ]);
    }
}
