<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends ApiController
{
    public function show(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->respond([
                'message' => 'Invalid verification link.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $this->respond([
            'message' => 'Email verified successfully.',
        ]);
    }
}
