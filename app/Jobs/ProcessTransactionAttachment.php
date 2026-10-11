<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TransactionAttachments\ProcessAttachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTransactionAttachment implements ShouldQueue
{
    use Queueable;

    public int $tries;
    public int $timeout;

    public function __construct(public readonly int $attachmentId)
    {
        $this->tries = (int) config('transaction_attachments.max_attempts');
        $this->timeout = (int) config('transaction_attachments.processing_timeout');
    }

    public function failed(?\Throwable $exception): void
    {
        \App\Models\TransactionAttachment::query()->whereKey($this->attachmentId)->whereNull('deleted_at')->where('status', '!=', 'ready')->update(['status' => 'failed', 'processing_started_at' => null, 'error_message' => 'No se pudo procesar el archivo. Intenta nuevamente.']);
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(ProcessAttachment $processor): void
    {
        $processor->execute($this->attachmentId);
    }
}
