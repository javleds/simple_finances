<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends ApiController
{
    public function store(LoginRequest $request, JwtTokenService $jwtTokenService): JsonResponse
    {
        $user = User::withoutGlobalScopes()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user instanceof User || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->respond([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        return $this->respond([
            'message' => 'Authenticated.',
            'data' => $user,
            'meta' => [
                'auth' => $jwtTokenService->generate($user),
            ],
        ]);
    }
}
