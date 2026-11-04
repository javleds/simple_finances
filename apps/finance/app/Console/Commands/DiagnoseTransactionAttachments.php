<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DiagnoseAttachments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiagnoseTransactionAttachments extends Command
{
    protected $signature = 'attachments:diagnose {--timeout=120}';
    protected $description = 'Verify attachment queue, storage and image conversion';

    public function handle(): int
    {
        $connection = config('transaction_attachments.connection');
        $retryAfter = config('queue.connections.'.$connection.'.retry_after');
        if ($connection !== 'database' || (config('queue.connections.database.connection') !== null && config('queue.connections.database.connection') !== config('database.default')) || (int) $retryAfter <= (int) config('transaction_attachments.processing_timeout')) {
            $this->error('Attachments require database queue and retry_after greater than processing_timeout.');
            return self::FAILURE;
        }
        $token = (string) Str::uuid();
        $disk = Storage::disk(config('transaction_attachments.disk'));
        $path = 'diagnostics/'.$token.'.result';
        DiagnoseAttachments::dispatch($token)->onConnection($connection)->onQueue('attachments');
        $deadline = microtime(true) + max(1, (int) $this->option('timeout'));
        while (microtime(true) < $deadline) {
            if ($disk->exists($path)) {
                $result = $disk->get($path);
                $disk->delete($path);
                if ($result === 'ok') {
                    $this->info('Attachment processing is ready.');
                    return self::SUCCESS;
                }
                $this->error($result);
                return self::FAILURE;
            }
            usleep(250000);
        }
        $this->error('Attachment diagnostic timed out.');
        return self::FAILURE;
    }
}
