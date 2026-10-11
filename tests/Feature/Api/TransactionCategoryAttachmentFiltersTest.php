<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->owner->id, 'name' => 'Shared travel']);
    $this->account->users()->sync([$this->owner->id, $this->other->id]);
    $this->personal = Category::create(['user_id' => $this->owner->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $this->owner->id]);
    $this->shared = Category::create(['account_id' => $this->account->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $this->owner->id]);
    $this->withHeaders(['Authorization' => 'Bearer '.app(JwtTokenService::class)->generate($this->owner)['token']]);
});

function categoryFilterTransaction(\Tests\TestCase $context, ?Category $category, float $amount = 10, array $attributes = []): Transaction
{
    return Transaction::factory()->outcome()->completed()->create(array_replace(['account_id' => $context->account->id, 'user_id' => $context->owner->id, 'category_id' => $category?->id, 'amount' => $amount, 'scheduled_at' => '2026-10-10', 'concept' => 'Receipt'], $attributes));
}

function categoryFilterAttachment(Transaction $transaction, string $status, bool $deleted = false): void
{
    TransactionAttachment::create(['transaction_id' => $transaction->id, 'user_id' => $transaction->user_id, 'upload_id' => (string) Str::uuid(), 'original_name' => 'invoice.xml', 'extension' => 'xml', 'status' => $status, 'size_bytes' => 10, 'reserved_bytes' => 10, 'deleted_at' => $deleted ? now() : null]);
}

it('groups selected categories and uncategorized while combining attachments and search with AND', function (bool $nested): void {
    $categorized = categoryFilterTransaction($this, $this->personal, 15);
    $uncategorized = categoryFilterTransaction($this, null, 25);
    categoryFilterAttachment($categorized, 'pending');
    categoryFilterAttachment($uncategorized, 'failed');
    categoryFilterTransaction($this, $this->shared, 100);
    $other = categoryFilterTransaction($this, null, 200, ['user_id' => $this->other->id]);
    if (! $nested) {
        categoryFilterAttachment($other, 'pending');
    }
    $base = $nested ? '/api/accounts/'.$this->account->id.'/transactions' : '/api/transactions';
    $response = $this->getJson($base.'?'.http_build_query(['category_ids' => [$this->personal->id], 'uncategorized' => 1, 'has_attachments' => 1, 'search' => 'Receipt', 'per_page' => 1]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    if (! $nested) {
        $response->assertJsonPath('meta.summary.outcome_total', 40.0);
    }
    $this->getJson($base.'?category_ids='.$this->personal->id.','.$this->shared->id)->assertOk()->assertJsonCount(2, 'data');
})->with([false, true]);

it('treats false as without attachments and excludes deleted attachments from presence', function (bool $nested): void {
    $pending = categoryFilterTransaction($this, null);
    $removed = categoryFilterTransaction($this, null);
    $none = categoryFilterTransaction($this, null);
    categoryFilterAttachment($pending, 'pending');
    categoryFilterAttachment($removed, 'ready', true);
    $base = $nested ? '/api/accounts/'.$this->account->id.'/transactions' : '/api/transactions';
    $response = $this->getJson($base.'?has_attachments=0')->assertOk()->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())->toBe(collect([$removed->id, $none->id])->sort()->values()->all());
    $this->getJson($base.'?has_attachments=1')->assertOk()->assertJsonPath('data.0.id', $pending->id)->assertJsonPath('data.0.attachments_count', 1);
})->with([false, true]);

it('rejects malformed filters consistently in both list endpoints', function (string $query): void {
    foreach (['/api/transactions', '/api/accounts/'.$this->account->id.'/transactions'] as $base) {
        $this->getJson($base.'?'.$query)->assertUnprocessable();
    }
})->with(['category_ids=bad', 'category_ids=0', 'category_ids=1,,2', 'category_ids[0]=1.2', 'category_ids[0][id]=1', 'uncategorized=true', 'has_attachments=2', 'has_attachments[]=1']);

it('limits category selections and returns empty for unrelated categories without leaking options', function (): void {
    $this->getJson('/api/transactions?'.http_build_query(['category_ids' => range(1, 101)]))->assertUnprocessable();
    categoryFilterTransaction($this, $this->personal);
    $this->getJson('/api/transactions?category_ids=999999')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.summary.outcome_total', 0.0);
});

it('returns only used categories in the visible scope independently of dates and disambiguates account names', function (): void {
    categoryFilterTransaction($this, $this->personal, 10, ['scheduled_at' => '2020-01-01']);
    categoryFilterTransaction($this, $this->shared, 10, ['user_id' => $this->other->id]);
    $unused = Category::create(['user_id' => $this->owner->id, 'name' => 'Unused', 'normalized_name' => 'unused', 'created_by_user_id' => $this->owner->id]);
    $privateAccount = Account::factory()->create(['user_id' => $this->other->id]);
    $privateAccount->users()->sync([$this->other->id]);
    $privateCategory = Category::create(['account_id' => $privateAccount->id, 'name' => 'Secret', 'normalized_name' => 'secret', 'created_by_user_id' => $this->other->id]);
    categoryFilterTransaction($this, $privateCategory, 10, ['account_id' => $privateAccount->id, 'user_id' => $this->other->id]);
    categoryFilterTransaction($this, $unused, 10, ['legacy_migrated_at' => now()]);
    $parent = categoryFilterTransaction($this, null);
    categoryFilterTransaction($this, $unused, 10, ['parent_transaction_id' => $parent->id]);
    $this->getJson('/api/transactions/filter-options?start_date=2026-10-01')->assertOk()->assertExactJson(['data' => ['categories' => [['id' => $this->personal->id, 'name' => 'Food', 'account_name' => null]]]]);
    $this->getJson('/api/accounts/'.$this->account->id.'/transactions/filter-options')->assertOk()->assertJsonCount(3, 'data.categories')->assertJsonPath('data.categories.1.account_name', 'Shared travel');
    $this->getJson('/api/accounts/'.$privateAccount->id.'/transactions/filter-options')->assertForbidden();
    $this->account->delete();
    $this->getJson('/api/transactions/filter-options')->assertOk()->assertJsonCount(0, 'data.categories');
});
