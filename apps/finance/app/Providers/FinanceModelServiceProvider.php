<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AccountInviteCreated;
use App\Events\AccountInviteCreatingRequested;
use App\Events\SubscriptionSaved;
use App\Events\SubscriptionSaving;

use function auth;
use function class_basename;

use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\FinancialGoal;
use FinSi\FinanceDomain\Models\FixedIncome;
use FinSi\FinanceDomain\Models\FixedOutcome;
use FinSi\FinanceDomain\Models\PartialFixedIncome;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Models\SubscriptionPayment;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

use function substr;

class FinanceModelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(static fn (string $model): string => 'Database\\Factories\\' . class_basename($model) . 'Factory');
        Factory::guessModelNamesUsing(static fn (Factory $factory): string => 'FinSi\\FinanceDomain\\Models\\' . substr(class_basename($factory), 0, -7));

        Relation::morphMap(['App\\Models\\User' => User::class]);

        foreach ([FinancialGoal::class, FixedIncome::class, FixedOutcome::class, PartialFixedIncome::class, Subscription::class, SubscriptionPayment::class] as $model) {
            $model::creating(static function (Model $record): void {
                if (auth()->check()) {
                    $record->setAttribute('user_id', auth()->id());
                }
            });
        }

        Transaction::creating(static function (Transaction $transaction): void {
            if (auth()->check() && empty($transaction->user_id)) {
                $transaction->user_id = auth()->id();
            }
        });

        AccountInvite::creating(static fn (AccountInvite $invite): mixed => Event::until(new AccountInviteCreatingRequested($invite)));
        AccountInvite::created(static fn (AccountInvite $invite): mixed => Event::dispatch(new AccountInviteCreated($invite)));
        Subscription::saving(static fn (Subscription $subscription): mixed => Event::until(new SubscriptionSaving($subscription)));
        Subscription::saved(static fn (Subscription $subscription): mixed => Event::dispatch(new SubscriptionSaved($subscription)));
    }
}
