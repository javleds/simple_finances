<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use App\Services\Auth\ResolveInvitePostAuthRedirect;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisterController extends ApiController
{
    public function store(
        RegisterRequest $request,
        JwtTokenService $jwtTokenService,
        ResolveInvitePostAuthRedirect $resolveInvitePostAuthRedirect,
    ): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'phone_number' => $request->string('phone_number')->toString() ?: null,
        ]);

        event(new Registered($user));
        $user->sendEmailVerificationNotification();

        $meta = [
            'auth' => $jwtTokenService->generate($user),
        ];
        $postAuthRedirect = $resolveInvitePostAuthRedirect->execute(
            $user,
            $request->string('post_auth_action')->toString() ?: null,
        );

        if ($postAuthRedirect !== null) {
            $meta['post_auth_redirect'] = $postAuthRedirect;
        }

        return $this->respond([
            'message' => 'User registered successfully.',
            'data' => $user,
            'meta' => $meta,
        ], 201);
    }
}
