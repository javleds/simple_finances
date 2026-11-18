<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use function app;

use App\Http\Controllers\Api\ApiController;
use App\Services\Auth\ResolveInvitePostAuthRedirect;
use App\Support\SpaUrl;

use function event;

use FinSi\FinanceDomain\Models\User;

use function hash_equals;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use function redirect;
use function sha1;

use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends ApiController
{
    public function show(
        Request $request,
        ResolveInvitePostAuthRedirect $resolveInvitePostAuthRedirect,
        int $id,
        string $hash,
    ): JsonResponse | Response {
        $user = User::withoutGlobalScopes()->whereNull('virtual_account_id')->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            if ($request->boolean('redirect_to_spa')) {
                return redirect()->away(app(SpaUrl::class)->to('email-verification', [
                    'status' => 'invalid',
                ]));
            }

            return $this->respond([
                'message' => 'Invalid verification link.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if ($request->boolean('redirect_to_spa')) {
            $postAuthRedirect = $resolveInvitePostAuthRedirect->execute(
                $user,
                ResolveInvitePostAuthRedirect::ACCOUNT_INVITES_ACTION,
            );

            if (null !== $postAuthRedirect) {
                return redirect()->away($postAuthRedirect['url']);
            }

            return redirect()->away(app(SpaUrl::class)->to('email-verification', [
                'status' => 'verified',
            ]));
        }

        return $this->respond([
            'message' => 'Email verified successfully.',
        ]);
    }
}
