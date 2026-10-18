<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use function abort_unless;

use App\Models\User;
use App\Services\Legal\GetLegalAcceptanceState;
use Closure;
use Illuminate\Http\Request;

use function response;

use Symfony\Component\HttpFoundation\Response;

class RequireCurrentLegalAcceptance
{
    public function __construct(private GetLegalAcceptanceState $state)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $state = $this->state->execute($user);

        if ($state['required']) {
            return response()->json([
                'message'   => 'Debes revisar y aceptar los documentos legales vigentes para continuar.',
                'code'      => 'legal_acceptance_required',
                'available' => $state['available'],
            ], 428);
        }

        return $next($request);
    }
}
