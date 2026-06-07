<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Services\Auth\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends ApiController
{
    public function delete(Request $request, JwtTokenService $jwtTokenService): JsonResponse
    {
        $payload = $request->attributes->get('jwt_payload');

        if (is_array($payload)) {
            $jwtTokenService->revoke($payload);
        }

        return $this->respond([
            'message' => 'Logged out.',
        ]);
    }
}
