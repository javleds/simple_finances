<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Auth\ResolveInvitePostAuthRedirect;
use App\Support\SpaUrl;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends ApiController
{
    public function show(
        Request $request,
        ResolveInvitePostAuthRedirect $resolveInvitePostAuthRedirect,
        int $id,
        string $hash
    ): JsonResponse|Response
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);

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

            if ($postAuthRedirect !== null) {
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
