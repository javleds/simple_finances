<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Tests;

use FinSi\FinanceDomain\FinanceDomainServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FinanceDomainServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('virtual_account_id')->nullable();
            $table->timestamp('virtual_retired_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('accounts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->boolean('uses_shared_categories')->default(false);
            $table->boolean('virtual')->default(false);
            $table->boolean('credit_card')->default(false);
            $table->decimal('balance')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('account_user', static function (Blueprint $table): void {
            $table->foreignId('account_id');
            $table->foreignId('user_id');
            $table->decimal('percentage')->default(100);
        });
        Schema::create('categories', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('account_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('account_invites', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('account_id');
            $table->string('email');
            $table->timestamps();
        });
        Schema::create('transactions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id');
            $table->foreignId('user_id');
            $table->decimal('amount');
            $table->string('type');
            $table->string('status');
            $table->string('concept')->default('Expense');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('parent_transaction_id')->nullable();
            $table->timestamps();
        });
        Schema::create('shared_transaction_notification_batches', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamps();
        });
        Schema::create('shared_transaction_notification_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id');
            $table->timestamps();
        });
        Schema::create('account_member_ledger_entries', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id');
            $table->foreignId('user_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('related_user_id')->nullable();
            $table->string('type');
            $table->decimal('amount');
            $table->timestamps();
        });
        Schema::create('legal_acceptances', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('terms_hash');
            $table->string('privacy_hash');
            $table->boolean('terms_accepted');
            $table->boolean('privacy_policy_accepted');
            $table->boolean('financial_data_consent');
            $table->boolean('is_adult');
            $table->timestamp('created_at');
        });
    }
}
