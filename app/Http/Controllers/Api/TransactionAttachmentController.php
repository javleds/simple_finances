<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessTransactionAttachment;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Services\TransactionAttachments\AcceptTransactionAttachment;
use App\Services\TransactionAttachments\AuthorizeAttachmentAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionAttachmentController extends ApiController
{
    public function __construct(private readonly AuthorizeAttachmentAccess $authorize)
    {
    }

    public function config(): JsonResponse
    {
        return $this->respond(['data' => collect(config('transaction_attachments'))->only(['max_files', 'max_file_bytes', 'max_transaction_bytes', 'accepted_extensions', 'poll_interval_ms'])->all()]);
    }

    public function index(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize->execute($transaction, $request->user()->id);
        $attachments = $transaction->attachments()->orderBy('id')->get();
        return $this->respond(['data' => $attachments->map(fn (TransactionAttachment $attachment): array => $this->metadata($attachment, $transaction, $request)), 'meta' => ['can_manage' => $transaction->user_id === $request->user()->id, 'count' => $attachments->count(), 'total_bytes' => $attachments->sum('size_bytes')]]);
    }

    public function store(Request $request, Transaction $transaction, AcceptTransactionAttachment $accept): JsonResponse
    {
        $this->authorize->execute($transaction, $request->user()->id, true);
        $request->validate(['file' => ['required', 'file'], 'upload_id' => ['required', 'uuid']]);
        $attachment = $accept->execute($transaction, $request->user()->id, $request->file('file'), $request->string('upload_id')->toString());
        return $this->respond(['data' => $this->metadata($attachment, $transaction, $request)], 202);
    }

    public function download(Request $request, Transaction $transaction, TransactionAttachment $attachment): BinaryFileResponse
    {
        $this->authorize->execute($transaction, $request->user()->id, attachment: $attachment);
        abort_unless($attachment->status === 'ready' && $attachment->path !== null, 409, 'El archivo todavía no está disponible.');
        $disk = Storage::disk(config('transaction_attachments.disk'));
        abort_unless($disk->exists($attachment->path), 404);
        $name = $attachment->mime_type === 'image/webp' ? pathinfo($attachment->original_name, PATHINFO_FILENAME).'.webp' : $attachment->original_name;
        return response()->download($disk->path($attachment->path), $name, ['Content-Type' => $attachment->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function retry(Request $request, Transaction $transaction, TransactionAttachment $attachment): JsonResponse
    {
        $this->authorize->execute($transaction, $request->user()->id, true, $attachment);
        DB::transaction(function () use ($attachment): void {
            $current = TransactionAttachment::query()->lockForUpdate()->findOrFail($attachment->id);
            abort_unless($current->status === 'failed' && $current->source_path !== null && $current->deleted_at === null, 409, 'Vuelve a seleccionar el archivo para subirlo.');
            $current->update(['status' => 'pending', 'error_message' => null]);
            ProcessTransactionAttachment::dispatch($current->id)->onConnection(config('transaction_attachments.connection'))->onQueue('attachments');
        });
        return $this->respond(['data' => $this->metadata($attachment->fresh(), $transaction, $request)], 202);
    }

    public function delete(Request $request, Transaction $transaction, TransactionAttachment $attachment): JsonResponse
    {
        $this->authorize->execute($transaction, $request->user()->id, true, $attachment);
        $attachment->update(['deleted_at' => now()]);
        return $this->respondDeleted('Comprobante eliminado.');
    }

    private function metadata(TransactionAttachment $attachment, Transaction $transaction, Request $request): array
    {
        $manage = $transaction->user_id === $request->user()->id;
        return ['id' => $attachment->id, 'original_name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes, 'status' => $attachment->status, 'error_message' => $attachment->error_message, 'can_manage' => $manage, 'can_retry' => $manage && $attachment->status === 'failed' && $attachment->source_path !== null, 'created_at' => $attachment->created_at->toISOString()];
    }
}
