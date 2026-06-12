<?php

namespace App\Services\Accounts;

use App\Models\Account;

class AttachAccountToSignedInUser
{
    public function execute(Account $account): void
    {
        if (auth()->check()) {
            $account->users()->syncWithoutDetaching([
                auth()->id() => ['percentage' => 100.0],
            ]);
        }
    }
}
