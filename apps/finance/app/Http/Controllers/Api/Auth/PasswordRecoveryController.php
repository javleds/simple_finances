<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use function __;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\PasswordRecoveryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordRecoveryController extends ApiController
{
    public function store(PasswordRecoveryRequest $request): JsonResponse
    {
        $status = Password::sendResetLink([
            'email'              => $request->string('email')->toString(),
            'virtual_account_id' => null,
        ]);

        if (Password::RESET_LINK_SENT !== $status) {
            return $this->respond([
                'message' => __($status),
            ], 422);
        }

        return $this->respond([
            'message' => __($status),
        ]);
    }
}
