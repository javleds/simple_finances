<?php

declare(strict_types=1);

namespace App\Services\TransactionAttachments;

use App\Models\TransactionAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessAttachment
{
    public function __construct(private readonly ValidateAttachmentContent $validate, private readonly ConvertAttachmentImage $convert)
    {
    }

    private function cleanSource(int $attachmentId): void
    {
        DB::transaction(function () use ($attachmentId): void {
            DB::table('transaction_attachment_capacity')->where('id', 1)->lockForUpdate()->first();
            $current = TransactionAttachment::query()->lockForUpdate()->find($attachmentId);
            if ($current === null || $current->status !== 'ready' || $current->source_path === null) {
                return;
            }
            $disk = Storage::disk(config('transaction_attachments.disk'));
            if ($disk->exists($current->source_path) && ! $disk->delete($current->source_path)) {
                return;
            }
            DB::table('transaction_attachment_capacity')->where('id', 1)->decrement('reserved_bytes', $current->reserved_bytes - $current->size_bytes);
            $current->update(['source_path' => null, 'reserved_bytes' => $current->size_bytes]);
        });
    }

    public function execute(int $attachmentId): void
    {
        $disk = Storage::disk(config('transaction_attachments.disk'));
        $disk->makeDirectory('');
        $workspaceLock = fopen($disk->path('.image-workspace.lock'), 'c');
        $workspaceReserved = false;
        $lock = fopen($disk->path('.processing-'.$attachmentId.'.lock'), 'c');
        if ($lock === false) {
            throw new AttachmentProcessingException('No se pudo bloquear el procesamiento.');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new AttachmentProcessingException('El archivo continúa en procesamiento.');
        }
        $output = null;
        try {
            $attachment = TransactionAttachment::query()->find($attachmentId);
            if ($attachment === null || $attachment->deleted_at !== null || $attachment->transaction_id === null) {
                return;
            }
            if ($attachment->status === 'ready') {
                $this->cleanSource($attachmentId);
                return;
            }
            if ($attachment->source_path === null || ! $disk->exists($attachment->source_path)) {
                throw new AttachmentProcessingException('Vuelve a seleccionar el archivo para subirlo.');
            }
            $attachment->update(['status' => 'processing', 'processing_started_at' => now(), 'error_message' => null]);
            $imageExtension = ! in_array($attachment->extension, ['pdf', 'xml', 'zip'], true);
            if ($imageExtension || $attachment->extension === 'zip') {
                if ($workspaceLock === false || ! flock($workspaceLock, LOCK_EX | LOCK_NB)) {
                    throw new AttachmentProcessingException('Otro comprobante está en procesamiento. Intenta nuevamente.');
                }
                DB::transaction(function () use ($disk): void {
                    $capacity = DB::table('transaction_attachment_capacity')->where('id', 1)->lockForUpdate()->first();
                    $scratch = (int) config('transaction_attachments.processing_disk_bytes');
                    $free = disk_free_space($disk->path(''));
                    if ($capacity->reserved_bytes + $scratch > config('transaction_attachments.max_storage_bytes') || $free === false || $free - $scratch < config('transaction_attachments.min_free_disk_bytes')) {
                        throw new AttachmentProcessingException('No hay espacio disponible para procesar la imagen. Intenta más tarde.');
                    }
                    DB::table('transaction_attachment_capacity')->where('id', 1)->increment('reserved_bytes', $scratch);
                });
                $workspaceReserved = true;
            }
            $mime = $this->validate->execute($disk->path($attachment->source_path), $attachment->extension);
            $dimensions = [];
            $output = 'files/'.$attachmentId;
            $disk->makeDirectory('files');
            if (str_starts_with($mime, 'image/')) {
                $dimensions = $this->convert->execute($disk->path($attachment->source_path), $disk->path($output));
                $mime = 'image/webp';
            }
            if (! str_starts_with($mime, 'image/')) {
                $disk->copy($attachment->source_path, $output);
            }
            $size = $disk->size($output);
            DB::transaction(function () use ($attachmentId, $disk, $output, $size, $mime, $dimensions): void {
                DB::table('transaction_attachment_capacity')->where('id', 1)->lockForUpdate()->first();
                $current = TransactionAttachment::query()->lockForUpdate()->find($attachmentId);
                if ($current === null || $current->deleted_at !== null || $current->transaction_id === null) {
                    $disk->delete($output);
                    return;
                }
                $otherBytes = TransactionAttachment::query()->where('transaction_id', $current->transaction_id)->whereNull('deleted_at')->whereKeyNot($current->id)->sum('size_bytes');
                if ($otherBytes + $size > config('transaction_attachments.max_transaction_bytes')) {
                    throw new AttachmentProcessingException('El resultado excede el espacio permitido para este movimiento.');
                }
                $sourceBytes = $disk->size($current->source_path);
                DB::table('transaction_attachment_capacity')->where('id', 1)->increment('reserved_bytes', $sourceBytes + $size - $current->reserved_bytes);
                $current->update([...$dimensions, 'path' => $output, 'size_bytes' => $size, 'reserved_bytes' => $sourceBytes + $size, 'mime_type' => $mime, 'status' => 'ready', 'processing_started_at' => null]);
            });
            $output = null;
            $this->cleanSource($attachmentId);
        } catch (Throwable $exception) {
            if ($output !== null) {
                $disk->delete($output);
            }
            TransactionAttachment::query()->whereKey($attachmentId)->whereNull('deleted_at')->whereNotNull('transaction_id')->where('status', '!=', 'ready')->update(['status' => 'failed', 'error_message' => $exception instanceof AttachmentProcessingException ? $exception->getMessage() : 'No se pudo procesar el archivo. Intenta nuevamente.', 'processing_started_at' => null]);
            throw $exception;
        } finally {
            if ($workspaceReserved) {
                DB::table('transaction_attachment_capacity')->where('id', 1)->decrement('reserved_bytes', (int) config('transaction_attachments.processing_disk_bytes'));
            }
            if (is_resource($workspaceLock)) {
                flock($workspaceLock, LOCK_UN);
                fclose($workspaceLock);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
