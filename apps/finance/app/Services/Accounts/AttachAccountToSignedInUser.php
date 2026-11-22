<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use function auth;

use FinSi\FinanceDomain\Models\Account;

class AttachAccountToSignedInUser
{
    public function execute(Account $account): void
    {
        if (auth()->check()) {
            $account->users()->syncWithoutDetaching([
                auth()->id() => ['percentage' => '100.00'],
            ]);
        }
    }
}
