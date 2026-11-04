<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\FinancialGoal;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Policies\AccountPolicy;
use FinSi\FinanceDomain\Policies\FinancialGoalPolicy;
use FinSi\FinanceDomain\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FinanceDomainServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(FinancialGoal::class, FinancialGoalPolicy::class);
    }
}
