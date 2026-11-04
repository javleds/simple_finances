<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\ResendEmailVerificationRequest;
use App\Services\Auth\SendEmailVerificationNotificationByEmail;
use Illuminate\Http\JsonResponse;

class EmailVerificationNotificationByEmailController extends ApiController
{
    public function store(
        ResendEmailVerificationRequest $request,
        SendEmailVerificationNotificationByEmail $sendEmailVerificationNotificationByEmail,
    ): JsonResponse {
        $sendEmailVerificationNotificationByEmail->handle(
            $request->string('email')->toString(),
        );

        return $this->respond([
            'message' => 'If the account exists and the email is not verified, a verification link will be sent.',
        ]);
    }
}
