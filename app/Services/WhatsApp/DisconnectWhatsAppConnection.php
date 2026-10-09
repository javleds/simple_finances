<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppVerificationCode;
use Illuminate\Support\Facades\DB;

class DisconnectWhatsAppConnection
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            WhatsAppConnection::query()->where('user_id', $user->id)->delete();
            WhatsAppVerificationCode::query()->where('user_id', $user->id)
                ->whereIn('status', ['sending', 'sent'])->update(['status' => 'invalidated']);
        });
    }
}
