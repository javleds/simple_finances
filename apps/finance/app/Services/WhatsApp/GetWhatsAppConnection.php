<?php

namespace App\Services\WhatsApp;

use FinSi\FinanceDomain\Queries\UserOwnedResources;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Models\WhatsAppConnection;
use FinSi\FinanceDomain\Models\WhatsAppVerificationCode;

class GetWhatsAppConnection
{
    public function __construct(private readonly UserOwnedResources $userOwnedResources) {}

    public function execute(User $user): array
    {
        $connection = $this->userOwnedResources->constrain(WhatsAppConnection::query(), (int) $user->id)->first();

        if ($connection !== null) {
            return ['status' => 'linked', 'phone_number' => $connection->phone_number, 'expires_at' => null, 'resend_available_at' => null];
        }

        $code = $this->userOwnedResources->constrain(WhatsAppVerificationCode::query(), (int) $user->id)
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
