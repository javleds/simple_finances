<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use App\Services\Categories\ConvertAccountCategoryCatalog;

function categoryHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . app(JwtTokenService::class)->generate($user)['token']];
}

it('isolates personal catalogs and rejects normalized duplicate names', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->withHeaders(categoryHeaders($user))->postJson('/api/categories', ['name' => '  Food   shopping '])->assertCreated()->assertJsonPath('data.name', 'Food shopping');
    $this->getJson('/api/categories?search[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('search');
    $this->postJson('/api/categories', ['name' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/categories', ['name' => 'food shopping'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->withHeaders(categoryHeaders($other))->getJson('/api/categories')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson('/api/categories', ['name' => 'Food shopping'])->assertCreated();
});

it('lets members create shared categories while only the owner can manage them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'uses_shared_categories' => true]);
    $account->users()->attach([$owner->id, $member->id]);
    $base = '/api/accounts/' . $account->id . '/categories';
    $id = $this->withHeaders(categoryHeaders($member))->postJson($base, ['name' => 'Food'])->assertCreated()->assertJsonPath('data.scope', 'shared')->assertJsonPath('data.can_manage', false)->json('data.id');
    $this->putJson($base . '/' . $id, ['name' => 'Groceries'])->assertForbidden();
    $this->deleteJson($base . '/' . $id)->assertForbidden();
    $this->withHeaders(categoryHeaders($outsider))->getJson($base)->assertForbidden();
    $this->withHeaders(categoryHeaders($owner))->putJson($base . '/' . $id, ['name' => 'Groceries'])->assertOk();
});

it('requires an explicit deletion decision and reclassifies all members without changing finances', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'uses_shared_categories' => true, 'balance' => 125]);
    $account->users()->attach([$owner->id, $member->id]);
    $source = Category::create(['account_id' => $account->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $owner->id]);
    $target = Category::create(['account_id' => $account->id, 'name' => 'Other', 'normalized_name' => 'other', 'created_by_user_id' => $owner->id]);
    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'user_id' => $member->id, 'category_id' => $source->id, 'amount' => 12]);
    $url = '/api/accounts/' . $account->id . '/categories/' . $source->id;
    $this->withHeaders(categoryHeaders($owner))->deleteJson($url)->assertUnprocessable();
    $this->deleteJson($url, ['action' => 'reassign', 'target_category_id' => $source->id])->assertUnprocessable();
    $this->deleteJson($url, ['action' => 'reassign', 'target_category_id' => $target->id])->assertOk();
    expect($transaction->fresh()->category_id)->toBe($target->id)->and($transaction->fresh()->amount)->toBe(12.0)->and($account->fresh()->balance)->toBe(125.0);
    $this->deleteJson('/api/accounts/' . $account->id . '/categories/' . $target->id, ['action' => 'uncategorize'])->assertOk();
    expect($transaction->fresh()->category_id)->toBeNull();
});

it('copies only categories used by the account and preserves a shared catalog on repeated conversion', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $personal = Category::create(['user_id' => $owner->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $owner->id]);
    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id, 'category_id' => $personal->id]);
    app(ConvertAccountCategoryCatalog::class)->execute($account);
    app(ConvertAccountCategoryCatalog::class)->execute($account);
    expect($account->fresh()->uses_shared_categories)->toBeTrue()->and($transaction->fresh()->category_id)->not->toBe($personal->id)->and(Category::where('account_id', $account->id)->count())->toBe(1)->and($personal->fresh())->not->toBeNull();
});

it('validates transaction category scope and preserves omitted categories on update', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Salary', 'normalized_name' => 'salary', 'created_by_user_id' => $user->id]);
    $foreign = Category::create(['user_id' => $other->id, 'name' => 'Salary', 'normalized_name' => 'salary', 'created_by_user_id' => $other->id]);
    $payload = ['account_id' => $account->id, 'type' => 'income', 'status' => 'completed', 'concept' => 'Salary', 'amount' => 10, 'scheduled_at' => '2026-10-10'];
    $this->withHeaders(categoryHeaders($user))->postJson('/api/transactions', $payload + ['category_id' => $foreign->id])->assertUnprocessable();
    $id = $this->postJson('/api/transactions', $payload + ['category_id' => $category->id])->assertCreated()->assertJsonPath('data.category.name', 'Salary')->json('data.id');
    $this->putJson('/api/transactions/' . $id, $payload)->assertOk()->assertJsonPath('data.category_id', $category->id);
    $this->putJson('/api/transactions/' . $id, $payload + ['category_id' => null])->assertOk()->assertJsonPath('data.category_id', null);
});

it('preserves the current classification when updating a stale transaction without a category field', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Salary', 'normalized_name' => 'salary', 'created_by_user_id' => $user->id]);
    $stale = Transaction::factory()->income()->create(['account_id' => $account->id, 'user_id' => $user->id, 'category_id' => $category->id]);
    auth()->login($user);
    app(App\Services\Categories\DeleteCategory::class)->execute($category, 'uncategorize', null);
    app(App\Services\Transaction\TransactionUpdater::class)->execute($stale, App\Dto\TransactionFormDto::fromFormArray([
        'account_id'   => $account->id,
        'type'         => 'income',
        'status'       => 'completed',
        'concept'      => 'Updated salary',
        'amount'       => 10,
        'scheduled_at' => '2026-10-10',
    ]));
    expect($stale->fresh()->category_id)->toBeNull();
});

it('resolves an account personal catalog and allows equal names across personal and shared catalogs', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);
    $this->withHeaders(categoryHeaders($owner))->postJson('/api/categories', ['name' => 'Food'])->assertCreated();
    $this->getJson('/api/accounts/' . $account->id . '/categories')->assertOk()->assertJsonPath('meta.scope', 'personal')->assertJsonCount(1, 'data');
    $account->update(['uses_shared_categories' => true]);
    $this->postJson('/api/accounts/' . $account->id . '/categories', ['name' => 'Food'])->assertCreated()->assertJsonPath('data.scope', 'shared');
});

it('counts shared use by all members and rejects reassignment outside the catalog', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'uses_shared_categories' => true]);
    $account->users()->attach([$owner->id, $member->id]);
    $shared = Category::create(['account_id' => $account->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $owner->id]);
    $personal = Category::create(['user_id' => $owner->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $owner->id]);
    foreach ([$owner, $member] as $user) {
        Transaction::factory()->create(['account_id' => $account->id, 'user_id' => $user->id, 'category_id' => $shared->id]);
    }
    $url = '/api/accounts/' . $account->id . '/categories';
    $this->withHeaders(categoryHeaders($member))->getJson($url)->assertOk()->assertJsonPath('data.0.transactions_count', 2);
    $this->withHeaders(categoryHeaders($owner))->deleteJson($url . '/' . $shared->id, ['action' => 'reassign', 'target_category_id' => $personal->id])->assertUnprocessable()->assertJsonValidationErrors('target_category_id');
    expect(Transaction::where('category_id', $shared->id)->count())->toBe(2);
});

it('converts categories when adding a member through the API and retains them after removal', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id, ['percentage' => 100]);
    $category = Category::create(['user_id' => $owner->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $owner->id]);
    $transaction = Transaction::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id, 'category_id' => $category->id]);
    $this->withHeaders(categoryHeaders($owner))->postJson('/api/accounts/' . $account->id . '/users', ['user_id' => $member->id, 'percentage' => 0])->assertCreated();
    $sharedId = $transaction->fresh()->category_id;
    expect($account->fresh()->uses_shared_categories)->toBeTrue()->and($sharedId)->not->toBe($category->id);
    $this->deleteJson('/api/accounts/' . $account->id . '/users/' . $member->id)->assertOk();
    expect($account->fresh()->uses_shared_categories)->toBeTrue()->and($transaction->fresh()->category_id)->toBe($sharedId);
    $this->withHeaders(categoryHeaders($member))->getJson('/api/accounts/' . $account->id . '/categories')->assertForbidden();
});

it('changes only category references when deleting a category used by a split shared expense', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'uses_shared_categories' => true]);
    $account->users()->attach([$owner->id => ['percentage' => 50], $member->id => ['percentage' => 50]]);
    $category = Category::create(['account_id' => $account->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $owner->id]);
    $this->withHeaders(categoryHeaders($owner))->postJson('/api/accounts/' . $account->id . '/transactions', [
        'type'                => 'outcome', 'status' => 'completed', 'concept' => 'Shared meal', 'amount' => 100,
        'paid_by_user_id'     => $owner->id, 'payment_source' => 'member_out_of_pocket',
        'split_between_users' => true, 'category_id' => $category->id, 'scheduled_at' => '2026-10-10',
        'user_payments'       => [['user_id' => $owner->id, 'percentage' => 50], ['user_id' => $member->id, 'percentage' => 50]],
    ])->assertCreated();
    App\Models\AccountBalanceSnapshot::create(['account_id' => $account->id, 'user_id' => $owner->id, 'observed_balance' => 0, 'previous_balance' => 0, 'delta' => 0, 'observed_at' => '2026-10-10']);
    $beforeLedger = Illuminate\Support\Facades\DB::table('account_member_ledger_entries')->orderBy('id')->get()->toArray();
    $beforeAllocations = Illuminate\Support\Facades\DB::table('transaction_allocations')->orderBy('id')->get()->toArray();
    $beforeSnapshots = Illuminate\Support\Facades\DB::table('account_balance_snapshots')->orderBy('id')->get()->toArray();
    $beforeTransactions = Transaction::withoutGlobalScopes()->orderBy('id')->get()->map(static fn (Transaction $transaction): array => $transaction->getAttributes())->all();
    $balance = $account->fresh()->balance;
    expect($beforeLedger)->not->toBeEmpty()->and($beforeAllocations)->not->toBeEmpty()->and($beforeSnapshots)->not->toBeEmpty();
    $this->deleteJson('/api/accounts/' . $account->id . '/categories/' . $category->id, ['action' => 'uncategorize'])->assertOk();
    expect(Illuminate\Support\Facades\DB::table('account_member_ledger_entries')->orderBy('id')->get()->toArray())->toEqual($beforeLedger)
        ->and(Illuminate\Support\Facades\DB::table('transaction_allocations')->orderBy('id')->get()->toArray())->toEqual($beforeAllocations)
        ->and(Illuminate\Support\Facades\DB::table('account_balance_snapshots')->orderBy('id')->get()->toArray())->toEqual($beforeSnapshots)
        ->and($account->fresh()->balance)->toBe($balance);
    foreach ($beforeTransactions as $before) {
        $after = Transaction::withoutGlobalScopes()->findOrFail($before['id'])->getAttributes();
        unset($before['category_id'], $before['updated_at'], $after['category_id'], $after['updated_at']);
        expect($after)->toEqual($before);
    }
});
