<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Policies;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;

class AccountPolicy
{
    public function __construct(private readonly AuthorizeUserOwnedResource $ownership, private readonly AuthorizeAccountAccess $accounts)
    {
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return $this->accounts->isMember($account, $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Account $account): bool
    {
        return $this->ownership->isOwned($account, $user->id);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->ownership->isOwned($account, $user->id);
    }

    public function restore(User $user, Account $account): bool
    {
        return $this->ownership->isOwned($account, $user->id);
    }

    public function forceDelete(User $user, Account $account): bool
    {
        return $this->ownership->isOwned($account, $user->id);
    }
}
