<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use App\Services\Auth\ResolveInvitePostAuthRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends ApiController
{
    public function store(
        LoginRequest $request,
        JwtTokenService $jwtTokenService,
        ResolveInvitePostAuthRedirect $resolveInvitePostAuthRedirect,
    ): JsonResponse
    {
        $user = User::withoutGlobalScopes()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user instanceof User || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->respond([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

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
            'message' => 'Authenticated.',
            'data' => $user,
            'meta' => $meta,
        ]);
    }
}
