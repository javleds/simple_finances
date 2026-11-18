<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use function __;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Auth\PasswordResetRequest;

use function event;

use FinSi\FinanceDomain\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends ApiController
{
    public function update(PasswordResetRequest $request): JsonResponse
    {
        $status = Password::reset(
            [...$request->validated(), 'virtual_account_id' => null],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password'       => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if (Password::PASSWORD_RESET !== $status) {
            return $this->respond([
                'message' => __($status),
            ], 422);
        }

        return $this->respond([
            'message' => __($status),
        ]);
    }
}
