<?php

namespace App\Services\Auth;

use App\Models\User;

class SendEmailVerificationNotificationByEmail
{
    public function handle(string $email): void
    {
        $user = User::withoutGlobalScopes()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
