<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\PasswordRecoveryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordRecoveryController extends ApiController
{
    public function store(PasswordRecoveryRequest $request): JsonResponse
    {
        $status = Password::sendResetLink([
            'email' => $request->string('email')->toString(),
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->respond([
                'message' => __($status),
            ], 422);
        }

        return $this->respond([
            'message' => __($status),
        ]);
    }
}
