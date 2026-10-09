<?php

namespace App\Jobs;

use App\Models\WebhookReceipt;
use App\Services\Webhooks\ProcessWhatsAppReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWebhookReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $receiptId)
    {
        $this->onConnection('database');
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(ProcessWhatsAppReceipt $processor): void
    {
        $processor->handle($this->receiptId);
    }

    public function failed(?Throwable $exception): void
    {
        WebhookReceipt::query()->whereKey($this->receiptId)
            ->where('status', '!=', 'processed')
            ->update(['status' => 'failed', 'last_error' => 'Webhook processing failed after all attempts.']);
    }
}
