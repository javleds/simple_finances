<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessWebhookReceipt;
use App\Models\WebhookReceipt;
use App\Services\Webhooks\ProcessWhatsAppReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.verify_token' => 'verify-secret',
            'services.whatsapp.app_secret' => 'app-secret',
            'services.whatsapp.phone_number_id' => '123456',
            'queue.default' => 'database',
            'queue.connections.database.connection' => null,
            'queue.connections.database.after_commit' => true,
        ]);
    }

    public function test_verification_returns_only_the_challenge(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-secret&hub.challenge=00123')
            ->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->assertContent('00123');
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=00123')
            ->assertForbidden();
    }

    public function test_signed_request_persists_encrypted_receipt_and_job_before_acknowledgement(): void
    {
        $payload = $this->payload();
        $this->sendWebhook($payload)->assertOk();
        $receipt = WebhookReceipt::query()->sole();
        $this->assertSame('pending', $receipt->status);
        $this->assertSame($payload, $receipt->payload);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertStringNotContainsString('sender-phone', DB::table('webhook_receipts')->value('payload'));
        $job = json_decode(DB::table('jobs')->value('payload'), true);
        $this->assertSame(ProcessWebhookReceipt::class, $job['displayName']);
        $this->assertStringNotContainsString('sender-phone', $job['data']['command']);
    }

    public function test_duplicate_delivery_creates_only_one_job(): void
    {
        $this->sendWebhook($this->payload())->assertOk();
        $this->sendWebhook($this->payload())->assertOk();
        $this->assertDatabaseCount('webhook_receipts', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_database_worker_processes_the_persisted_job(): void
    {
        $this->sendWebhook($this->payload())->assertOk();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])
            ->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('processed', WebhookReceipt::query()->sole()->status);
    }

    public function test_status_events_are_accepted_and_malformed_events_are_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['entry'][0]['changes'][0]['value']['messages']);
        $payload['entry'][0]['changes'][0]['value']['statuses'] = [['id' => 'message-id', 'status' => 'delivered']];
        $this->sendWebhook($payload)->assertOk();
        $this->sendWebhook(['object' => 'whatsapp_business_account', 'entry' => []])->assertUnprocessable();
        $this->assertDatabaseCount('webhook_receipts', 1);
    }

    public function test_exhausted_job_is_recorded_and_queue_retry_recovers_it(): void
    {
        $this->sendWebhook($this->payload())->assertOk();
        $receipt = WebhookReceipt::query()->sole();
        $receipt->update(['provider' => 'unsupported']);
        DB::table('jobs')->update(['attempts' => 4]);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])
            ->assertSuccessful();
        $this->assertSame('failed', $receipt->fresh()->status);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
        $receipt->update(['provider' => 'whatsapp']);
        $this->artisan('queue:retry', ['id' => ['all']])->assertSuccessful();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])
            ->assertSuccessful();
        $this->assertSame('processed', $receipt->fresh()->status);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_invalid_signature_or_phone_does_not_persist(): void
    {
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $body)->assertForbidden();
        $payload = $this->payload();
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = 'wrong';
        $this->sendWebhook($payload)->assertUnprocessable();
        $this->assertDatabaseCount('webhook_receipts', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_queue_failure_rolls_back_receipt(): void
    {
        Queue::shouldReceive('connection')->with('database')->andThrow(new \RuntimeException('private details'));
        $this->sendWebhook($this->payload())->assertStatus(503)->assertContent('Webhook unavailable');
        $this->assertDatabaseCount('webhook_receipts', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_incompatible_queue_configuration_does_not_acknowledge(): void
    {
        config(['queue.default' => 'sync']);
        $this->sendWebhook($this->payload())->assertStatus(503);
        config(['queue.default' => 'database', 'queue.connections.database.connection' => 'different']);
        $this->sendWebhook($this->payload())->assertStatus(503);
        config(['queue.connections.database.connection' => null, 'queue.connections.database.retry_after' => 60]);
        $this->sendWebhook($this->payload())->assertStatus(503);
        $this->assertDatabaseCount('webhook_receipts', 0);
    }

    public function test_processing_is_repeatable_and_failed_receipts_can_be_retried(): void
    {
        $this->sendWebhook($this->payload())->assertOk();
        $receipt = WebhookReceipt::query()->sole();
        $job = new ProcessWebhookReceipt($receipt->id);
        $job->failed(new \RuntimeException('sensitive error'));
        $this->assertSame('failed', $receipt->fresh()->status);
        $this->assertStringNotContainsString('sensitive', $receipt->fresh()->last_error);
        $job->handle(app(ProcessWhatsAppReceipt::class));
        $processedAt = $receipt->fresh()->processed_at;
        $this->travel(1)->minutes();
        $job->handle(app(ProcessWhatsAppReceipt::class));
        $this->assertSame('processed', $receipt->fresh()->status);
        $this->assertTrue($processedAt->equalTo($receipt->fresh()->processed_at));
        $this->assertNull($receipt->fresh()->last_error);
    }

    private function payload(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'business-id',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => '123456'],
                        'messages' => [['id' => 'message-id', 'from' => 'sender-phone', 'type' => 'text', 'text' => ['body' => 'Hello']]],
                    ],
                ]],
            ]],
        ];
    }

    private function sendWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret'),
        ], $body);
    }
}
