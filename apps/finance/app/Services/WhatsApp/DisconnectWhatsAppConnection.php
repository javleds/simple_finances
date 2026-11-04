<?php

namespace App\Services\WhatsApp;

use FinSi\FinanceDomain\Queries\UserOwnedResources;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Models\WhatsAppConnection;
use FinSi\FinanceDomain\Models\WhatsAppVerificationCode;
use Illuminate\Support\Facades\DB;

class DisconnectWhatsAppConnection
{
    public function __construct(private readonly UserOwnedResources $userOwnedResources) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->userOwnedResources->constrain(WhatsAppConnection::query(), (int) $user->id)->delete();
            $this->userOwnedResources->constrain(WhatsAppVerificationCode::query(), (int) $user->id)
                ->whereIn('status', ['sending', 'sent'])->update(['status' => 'invalidated']);
        });
    }
}
