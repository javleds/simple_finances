<?php

declare(strict_types=1);

namespace App\Services\Auth;

use FinSi\FinanceDomain\Models\User;

class SendEmailVerificationNotificationByEmail
{
    public function handle(string $email): void
    {
        $user = User::withoutGlobalScopes()
            ->where('email', $email)
            ->whereNull('virtual_account_id')
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
