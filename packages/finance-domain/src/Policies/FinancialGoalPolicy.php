<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Policies;

use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Models\FinancialGoal;
use FinSi\FinanceDomain\Models\User;

class FinancialGoalPolicy
{
    public function __construct(private readonly AuthorizeUserOwnedResource $ownership)
    {
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FinancialGoal $financialGoal): bool
    {
        return $this->ownership->isOwned($financialGoal, $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FinancialGoal $financialGoal): bool
    {
        return $this->ownership->isOwned($financialGoal, $user->id);
    }

    public function delete(User $user, FinancialGoal $financialGoal): bool
    {
        return $this->ownership->isOwned($financialGoal, $user->id);
    }

    public function restore(User $user, FinancialGoal $financialGoal): bool
    {
        return $this->ownership->isOwned($financialGoal, $user->id);
    }

    public function forceDelete(User $user, FinancialGoal $financialGoal): bool
    {
        return $this->ownership->isOwned($financialGoal, $user->id);
    }
}
