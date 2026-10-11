<?php

declare(strict_types=1);

namespace App\Services\TransactionAttachments;

use App\Jobs\ProcessTransactionAttachment;
use App\Models\TransactionAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaintainAttachments
{
    public function execute(): void
    {
        $disk = Storage::disk(config('transaction_attachments.disk'));
        TransactionAttachment::query()->where(function ($query): void {
            $query->whereNotNull('deleted_at')->orWhereNull('transaction_id')->orWhere(function ($query): void {
                $query->where('status', 'failed')->where('updated_at', '<', now()->subHours((int) config('transaction_attachments.source_retention_hours')));
            });
        })->eachById(function (TransactionAttachment $attachment) use ($disk): void {
            $lock = fopen($disk->path('.processing-'.$attachment->id.'.lock'), 'c');
            if ($lock === false) {
                return;
            }
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                fclose($lock);
                return;
            }
            try {
                DB::transaction(function () use ($attachment, $disk): void {
                    DB::table('transaction_attachment_capacity')->where('id', 1)->lockForUpdate()->first();
                    $current = TransactionAttachment::query()->lockForUpdate()->find($attachment->id);
                    if ($current === null) {
                        return;
                    }
                    $remove = $current->deleted_at !== null || $current->transaction_id === null;
                    if (! $remove && ($current->status !== 'failed' || $current->updated_at->isAfter(now()->subHours((int) config('transaction_attachments.source_retention_hours'))))) {
                        return;
                    }
                    foreach (array_filter([$current->source_path, $current->path]) as $path) {
                        if ($disk->exists($path) && ! $disk->delete($path)) {
                            return;
                        }
                    }
                    DB::table('transaction_attachment_capacity')->where('id', 1)->decrement('reserved_bytes', $current->reserved_bytes);
                    if ($remove) {
                        $current->delete();
                        return;
                    }
                    $current->update(['source_path' => null, 'path' => null, 'reserved_bytes' => 0, 'error_message' => 'Vuelve a seleccionar el archivo para subirlo.']);
                });
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        });
        TransactionAttachment::query()->where('status', 'ready')->whereNotNull('source_path')->whereNull('deleted_at')->eachById(fn (TransactionAttachment $attachment) => app(ProcessAttachment::class)->execute($attachment->id));
        TransactionAttachment::query()->whereNull('deleted_at')->whereNotNull('transaction_id')->where(function ($query): void {
            $query->where('status', 'pending')->orWhere(function ($query): void {
                $query->where('status', 'processing')->where('processing_started_at', '<', now()->subSeconds((int) config('transaction_attachments.processing_timeout') * 3));
            });
        })->where('updated_at', '<', now()->subHours())->eachById(function (TransactionAttachment $attachment): void {
            ProcessTransactionAttachment::dispatch($attachment->id)->onConnection(config('transaction_attachments.connection'))->onQueue('attachments');
            $attachment->touch();
        });
        $workspaceLock = fopen($disk->path('.image-workspace.lock'), 'c');
        if ($workspaceLock !== false && flock($workspaceLock, LOCK_EX | LOCK_NB)) {
            foreach ($disk->allFiles('workspace') as $workspaceFile) {
                $disk->delete($workspaceFile);
            }
            DB::transaction(function (): void {
                DB::table('transaction_attachment_capacity')->where('id', 1)->lockForUpdate()->first();
                DB::table('transaction_attachment_capacity')->where('id', 1)->update(['reserved_bytes' => TransactionAttachment::query()->sum('reserved_bytes')]);
            });
            flock($workspaceLock, LOCK_UN);
        }
        if (is_resource($workspaceLock)) {
            fclose($workspaceLock);
        }
        foreach (['sources', 'files', 'diagnostics'] as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if ($disk->lastModified($path) > now()->subHours((int) config('transaction_attachments.source_retention_hours'))->timestamp) {
                    continue;
                }
                if (TransactionAttachment::query()->where('source_path', $path)->orWhere('path', $path)->exists()) {
                    continue;
                }
                $disk->delete($path);
            }
        }
    }
}
