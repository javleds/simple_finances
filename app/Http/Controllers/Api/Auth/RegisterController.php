<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Filament\Events\Auth\Registered as FilamentRegistered;
use Illuminate\Http\JsonResponse;

class RegisterController extends ApiController
{
    public function store(RegisterRequest $request, JwtTokenService $jwtTokenService): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'phone_number' => $request->string('phone_number')->toString() ?: null,
        ]);

        event(new FilamentRegistered($user));
        $user->sendEmailVerificationNotification();

        return $this->respond([
            'message' => 'User registered successfully.',
            'data' => $user,
            'meta' => [
                'auth' => $jwtTokenService->generate($user),
            ],
        ], 201);
    }
}
