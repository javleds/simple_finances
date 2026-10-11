<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

it('classifies existing categories from all users movements without changing references', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'uses_shared_categories' => true]);
    $categories = collect(['income', 'outcome', 'mixed', 'unused'])->mapWithKeys(static fn (string $name): array => [
        $name => Category::create(['account_id' => $account->id, 'name' => $name, 'normalized_name' => $name, 'created_by_user_id' => $owner->id]),
    ]);
    foreach (['income' => ['income'], 'outcome' => ['outcome'], 'mixed' => ['income', 'outcome']] as $name => $types) {
        foreach ($types as $type) {
            Transaction::factory()->create(['user_id' => $member->id, 'account_id' => $account->id, 'category_id' => $categories[$name]->id, 'type' => $type]);
        }
    }
    $references = Transaction::withoutGlobalScopes()->pluck('category_id', 'id')->all();
    $migration = require database_path('migrations/2026_10_11_000702_add_transaction_type_to_categories_table.php');
    $migration->down();
    auth()->login($owner);
    $migration->up();

    expect($categories['income']->fresh()->transaction_type)->toBe('income')
        ->and($categories['outcome']->fresh()->transaction_type)->toBe('outcome')
        ->and($categories['mixed']->fresh()->transaction_type)->toBe('both')
        ->and($categories['unused']->fresh()->transaction_type)->toBe('both')
        ->and(Transaction::withoutGlobalScopes()->pluck('category_id', 'id')->all())->toBe($references);
});
