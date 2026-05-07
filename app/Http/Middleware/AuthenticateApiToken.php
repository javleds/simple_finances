<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateApiToken
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || $bearerToken === '') {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->jwtTokenService->parse($bearerToken);
            $user = User::withoutGlobalScopes()->find($payload['sub']);

            if (! $user instanceof User) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            Auth::setUser($user);
            $request->attributes->set('jwt_payload', $payload);
            $request->attributes->set('jwt_token', $bearerToken);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
