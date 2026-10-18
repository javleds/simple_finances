<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Dto\Auth\RegisterUserData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Services\Auth\JwtTokenService;
use App\Services\Auth\RegisterUser;
use App\Services\Auth\ResolveInvitePostAuthRedirect;

use function event;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisterController extends ApiController
{
    public function store(
        RegisterRequest $request,
        JwtTokenService $jwtTokenService,
        ResolveInvitePostAuthRedirect $resolveInvitePostAuthRedirect,
        RegisterUser $registerUser,
    ): JsonResponse {
        $user = $registerUser->execute(new RegisterUserData(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('phone_number')->toString() ?: null,
            $request->acceptanceData(),
        ));

        event(new Registered($user));
        $user->sendEmailVerificationNotification();

        $meta = [
            'auth' => $jwtTokenService->generate($user),
        ];
        $postAuthRedirect = $resolveInvitePostAuthRedirect->execute(
            $user,
            $request->string('post_auth_action')->toString() ?: null,
        );

        if (null !== $postAuthRedirect) {
            $meta['post_auth_redirect'] = $postAuthRedirect;
        }

        return $this->respond([
            'message' => 'User registered successfully.',
            'data'    => $user,
            'meta'    => $meta,
        ], 201);
    }
}
