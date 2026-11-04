<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Policies;

use FinSi\FinanceDomain\Authorization\AuthorizeTransactionAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;

class TransactionPolicy
{
    public function __construct(private readonly AuthorizeUserOwnedResource $ownership, private readonly AuthorizeTransactionAccess $transactions)
    {
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->transactions->isVisible($transaction, $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->ownership->isOwned($transaction, $user->id);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->ownership->isOwned($transaction, $user->id);
    }

    public function restore(User $user, Transaction $transaction): bool
    {
        return $this->ownership->isOwned($transaction, $user->id);
    }

    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return $this->ownership->isOwned($transaction, $user->id);
    }
}
