<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use function abort_unless;

use Closure;

use function config;
use function ctype_digit;

use FinSi\FinanceDomain\Authorization\HasCurrentLegalAcceptance;
use FinSi\FinanceDomain\Models\User;

use function hash_equals;

use Illuminate\Http\Request;

use function is_string;
use function strlen;

use Symfony\Component\HttpFoundation\Response;

class AuthenticateServiceActor
{
    public function __construct(private readonly HasCurrentLegalAcceptance $acceptance)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('mcp_service.token');
        abort_unless(strlen($token) >= 32 && hash_equals($token, $request->bearerToken() ?? ''), 401);
        $actor = $request->header('X-Actor-User-Id', '');
        abort_unless(is_string($actor) && ctype_digit($actor) && (int) $actor > 0, 401);
        $user = User::query()->find((int) $actor);
        abort_unless($user instanceof User, 401);
        abort_unless($user->hasVerifiedEmail(), 403);
        $terms = (string) config('mcp_service.terms_hash');
        $privacy = (string) config('mcp_service.privacy_hash');
        abort_unless('' !== $terms && '' !== $privacy, 503);
        abort_unless($this->acceptance->execute((int) $user->id, $terms, $privacy), 403);
        $request->setUserResolver(fn (): User => $user);

        return $next($request);
    }
}
