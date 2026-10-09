<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppVerificationCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConfirmWhatsAppConnection
{
    public function execute(User $user, string $plainCode): void
    {
        try {
            $confirmed = DB::transaction(function () use ($user, $plainCode): bool {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if (WhatsAppConnection::query()->where('user_id', $user->id)->exists()) {
                    throw new HttpException(409, 'La cuenta ya está vinculada con WhatsApp.');
                }

                $code = WhatsAppVerificationCode::query()->where('user_id', $user->id)
                    ->where('status', 'sent')->latest('id')->lockForUpdate()->first();

                if ($code === null || $code->expires_at->lessThanOrEqualTo(now()) || $code->attempts >= 5) {
                    return false;
                }

                $code->increment('attempts');

                if (! Hash::check($plainCode, $code->code_hash)) {
                    return false;
                }

                WhatsAppConnection::query()->create([
                    'user_id' => $user->id,
                    'phone_number' => $code->phone_number,
                    'verified_at' => now(),
                ]);
                $code->update(['status' => 'consumed']);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            throw new HttpException(409, 'El teléfono ya está vinculado a otra cuenta.');
        }

        if (! $confirmed) {
            throw ValidationException::withMessages(['code' => 'El código es incorrecto, expiró o agotó sus intentos.']);
        }
    }
}
