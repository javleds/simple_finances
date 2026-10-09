<?php

namespace App\Services\Webhooks;

use App\Models\WebhookReceipt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcessWhatsAppReceipt
{
    public function handle(int $receiptId): void
    {
        DB::transaction(function () use ($receiptId): void {
            $receipt = WebhookReceipt::query()->lockForUpdate()->findOrFail($receiptId);

            if ($receipt->status === 'processed') {
                return;
            }

            if ($receipt->provider !== 'whatsapp' || ($receipt->payload['object'] ?? null) !== 'whatsapp_business_account') {
                throw new RuntimeException('Unsupported webhook receipt.');
            }

            $receipt->update(['status' => 'processing', 'last_error' => null]);
            $receipt->update(['status' => 'processed', 'processed_at' => now()]);
        });
    }
}
