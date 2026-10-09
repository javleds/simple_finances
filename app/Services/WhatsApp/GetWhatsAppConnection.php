<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppVerificationCode;

class GetWhatsAppConnection
{
    public function execute(User $user): array
    {
        $connection = WhatsAppConnection::query()->where('user_id', $user->id)->first();

        if ($connection !== null) {
            return ['status' => 'linked', 'phone_number' => $connection->phone_number, 'expires_at' => null, 'resend_available_at' => null];
        }

        $code = WhatsAppVerificationCode::query()->where('user_id', $user->id)
            ->where('status', 'sent')->where('attempts', '<', 5)->where('expires_at', '>', now())->latest('id')->first();

        if ($code !== null) {
            return [
                'status' => 'pending',
                'phone_number' => $code->phone_number,
                'expires_at' => $code->expires_at->toIso8601String(),
                'resend_available_at' => $code->created_at->addMinute()->toIso8601String(),
            ];
        }

        return ['status' => 'unlinked', 'phone_number' => null, 'expires_at' => null, 'resend_available_at' => null];
    }
}
