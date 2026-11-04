<?php

declare(strict_types=1);

namespace Tests\Feature;

use function config;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function now;
use function str_repeat;

use Tests\TestCase;

class FinancialMcpTest extends TestCase
{
    public function testServiceAndActorIdentityAreRequired(): void
    {
        $this->postJson('/mcp', [])->assertUnauthorized();
        $this->postJson('/mcp', [], ['Authorization' => 'Bearer ' . str_repeat('s', 32)])->assertUnauthorized();
        $this->postJson('/mcp', [], $this->headers(999))->assertUnauthorized();
    }

    public function testToolsListHasNoModelControlledIdentityAndAllNineTools(): void
    {
        $result = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $this->headers())->assertOk();
        $tools = $result->json('result.tools');
        self::assertCount(9, $tools);
        foreach ($tools as $tool) {
            self::assertArrayNotHasKey('actor_user_id', $tool['inputSchema']['properties']);
            self::assertArrayNotHasKey('user_id', $tool['inputSchema']['properties']);
            self::assertTrue($tool['annotations']['readOnlyHint']);
        }
    }

    public function testActorIsolatedAcrossRequestsAndMembershipRevocation(): void
    {
        $this->tool('get_account_balance', ['account_id' => 1])->assertOk()->assertJsonPath('result.structuredContent.data.balance', '100.00');
        $this->tool('get_account_balance', ['account_id' => 1], 2)->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.structuredContent.error.code', 'not_found');
        $this->tool('get_account_balance', ['account_id' => 2], 2)->assertOk()->assertJsonPath('result.structuredContent.data.balance', '200.00');
        DB::table('account_user')->where('user_id', 1)->delete();
        $this->tool('get_account_balance', ['account_id' => 1])->assertJsonPath('result.isError', true);
    }

    public function testForgedIdentityAndMalformedPeriodsFailWithoutQueries(): void
    {
        $this->tool('get_account_balance', ['account_id' => 1, 'user_id' => 2])->assertJsonPath('result.structuredContent.error.code', 'validation_error');
        $this->tool('list_expenses', ['start_date' => 'not-a-date', 'end_date' => '2026-01-31'])->assertJsonPath('result.structuredContent.error.code', 'validation_error');
        $this->tool('list_account_balances', ['limit' => 101])->assertJsonPath('result.structuredContent.error.code', 'validation_error');
    }

    public function testCurrentLegalAcceptanceIsCheckedOnEveryRequest(): void
    {
        DB::table('legal_acceptances')->where('user_id', 1)->delete();
        $this->tool('list_account_balances')->assertForbidden();
        config(['mcp_service.terms_hash' => '']);
        $this->tool('list_account_balances', [], 2)->assertStatus(503);
    }

    public function testReadsDoNotIssueWrites(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
        $this->tool('list_account_balances')->assertOk();
        self::assertNotEmpty($queries);
        foreach ($queries as $query) {
            self::assertMatchesRegularExpression('/^select /i', $query);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['mcp_service.token' => str_repeat('s', 32), 'mcp_service.terms_hash' => 'terms', 'mcp_service.privacy_hash' => 'privacy']);
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
        });
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('balance');
            $table->boolean('virtual')->default(false);
            $table->boolean('credit_card')->default(false);
            $table->softDeletes();
        });
        Schema::create('account_user', function (Blueprint $table): void {
            $table->integer('account_id');
            $table->integer('user_id');
        });
        Schema::create('legal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->integer('user_id');
            $table->string('terms_hash');
            $table->string('privacy_hash');
            $table->boolean('terms_accepted');
            $table->boolean('privacy_policy_accepted');
            $table->boolean('financial_data_consent');
            $table->boolean('is_adult');
        });
        foreach ([1, 2] as $id) {
            DB::table('users')->insert(['id' => $id, 'name' => 'User ' . $id, 'email' => $id . '@example.test', 'email_verified_at' => now()]);
            DB::table('accounts')->insert(['id' => $id, 'name' => 'Account ' . $id, 'balance' => $id * 100]);
            DB::table('account_user')->insert(['user_id' => $id, 'account_id' => $id]);
            DB::table('legal_acceptances')->insert(['user_id' => $id, 'terms_hash' => 'terms', 'privacy_hash' => 'privacy', 'terms_accepted' => true, 'privacy_policy_accepted' => true, 'financial_data_consent' => true, 'is_adult' => true]);
        }
    }

    private function headers(int $actor = 1): array
    {
        return ['Authorization' => 'Bearer ' . str_repeat('s', 32), 'X-Actor-User-Id' => (string) $actor];
    }

    private function tool(string $name, array $arguments = [], int $actor = 1): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => (object) $arguments]], $this->headers($actor));
    }
}
