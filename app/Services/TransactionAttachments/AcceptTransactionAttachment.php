<?php

declare(strict_types=1);

namespace App\Services\TransactionAttachments;

use App\Jobs\ProcessTransactionAttachment;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AcceptTransactionAttachment
{
    public function execute(Transaction $transaction, int $userId, UploadedFile $file, string $uploadId): TransactionAttachment
    {
        if (config('queue.connections.database.connection') !== null && config('queue.connections.database.connection') !== config('database.default')) {
            throw new \RuntimeException('Attachments require the application database queue connection.');
        }
        return DB::transaction(function () use ($transaction, $userId, $file, $uploadId): TransactionAttachment {
            $capacity = DB::table('transaction_attachment_capacity')->where('id', 1)->lockForUpdate()->first();
            $transaction = Transaction::withoutGlobalScopes()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            app(AuthorizeAttachmentAccess::class)->execute($transaction, $userId, true);
            $existing = TransactionAttachment::query()->where('transaction_id', $transaction->id)->where('upload_id', $uploadId)->first();
            if ($existing !== null) {
                abort_if($existing->deleted_at !== null, 409, 'Esta subida ya fue eliminada.');
                return $existing;
            }
            $extension = strtolower($file->getClientOriginalExtension());
            $kind = in_array($extension, ['pdf', 'xml', 'zip'], true) ? $extension : 'image';
            $size = (int) $file->getSize();
            if (! in_array($extension, config('transaction_attachments.accepted_extensions'), true) || $size < 1 || $size > config("transaction_attachments.max_file_bytes.$kind")) {
                throw ValidationException::withMessages(['file' => 'El formato o tamaño del archivo no está permitido.']);
            }
            $attachments = TransactionAttachment::query()->where('transaction_id', $transaction->id)->whereNull('deleted_at');
            if ($attachments->count() >= config('transaction_attachments.max_files') || $attachments->sum('size_bytes') + $size > config('transaction_attachments.max_transaction_bytes')) {
                throw ValidationException::withMessages(['file' => 'Se alcanzó el límite de comprobantes o tamaño para este movimiento.']);
            }
            $reservation = $size + ($kind === 'image' ? (int) config('transaction_attachments.max_file_bytes.image') : $size);
            $disk = Storage::disk(config('transaction_attachments.disk'));
            $disk->makeDirectory('sources');
            $free = disk_free_space($disk->path(''));
            abort_if($capacity->reserved_bytes + $reservation > config('transaction_attachments.max_storage_bytes') || $free === false || $free - $reservation < config('transaction_attachments.min_free_disk_bytes'), 507, 'No hay espacio disponible para recibir comprobantes.');
            $path = 'sources/'.Str::uuid();
            try {
                $stream = fopen($file->getRealPath(), 'rb');
                try {
                    $disk->put($path, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $attachment = TransactionAttachment::query()->create([
                    'transaction_id' => $transaction->id, 'user_id' => $userId, 'upload_id' => $uploadId,
                    'original_name' => mb_substr(preg_replace('/[\x00-\x1f\x7f\/\\\\]/u', '_', $file->getClientOriginalName()), 0, 255),
                    'extension' => $extension, 'source_path' => $path, 'size_bytes' => $size, 'reserved_bytes' => $reservation, 'status' => 'pending',
                ]);
                DB::table('transaction_attachment_capacity')->where('id', 1)->increment('reserved_bytes', $reservation);
                ProcessTransactionAttachment::dispatch($attachment->id)->onConnection(config('transaction_attachments.connection'))->onQueue('attachments');
                return $attachment;
            } catch (Throwable $exception) {
                $disk->delete($path);
                throw $exception;
            }
        });
    }
}
