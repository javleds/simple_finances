<?php

namespace App\Services\Webhooks;

use App\Jobs\ProcessWebhookReceipt;
use App\Models\WebhookReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class ReceiveWhatsAppWebhook
{
    public function handle(string $body, array $payload): void
    {
        $connection = (new WebhookReceipt())->getConnectionName() ?? config('database.default');
        $queueConnection = config('queue.connections.database.connection') ?? config('database.default');

        if (config('queue.default') !== 'database'
            || config('queue.connections.database.driver') !== 'database'
            || $connection !== $queueConnection
            || (int) config('queue.connections.database.retry_after') <= 60) {
            throw new RuntimeException('Durable webhook queue is unavailable.');
        }

        DB::connection($connection)->transaction(function () use ($body, $payload): void {
            $receipt = WebhookReceipt::query()->createOrFirst([
                'provider' => 'whatsapp',
                'payload_hash' => hash('sha256', $body),
            ], [
                'payload' => $payload,
                'status' => 'pending',
            ]);

            if (! $receipt->wasRecentlyCreated) {
                return;
            }

            Queue::connection('database')->push(
                (new ProcessWebhookReceipt($receipt->id))->beforeCommit(),
            );
        });
    }
}
