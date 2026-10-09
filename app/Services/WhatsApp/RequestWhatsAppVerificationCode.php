<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppVerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RequestWhatsAppVerificationCode
{
    public function __construct(private readonly SendWhatsAppVerificationCode $sendCode)
    {
    }

    public function execute(User $user, string $phoneNumber): void
    {
        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $code = DB::transaction(function () use ($user, $phoneNumber, $plainCode): WhatsAppVerificationCode {
            DB::table('whatsapp_verification_locks')->insertOrIgnore(['phone_number' => $phoneNumber]);
            DB::table('whatsapp_verification_locks')->where('phone_number', $phoneNumber)->lockForUpdate()->first();
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (WhatsAppConnection::query()->where('user_id', $user->id)->orWhere('phone_number', $phoneNumber)->exists()) {
                throw new HttpException(409, 'El usuario o el teléfono ya tiene una vinculación con WhatsApp.');
            }

            foreach (['user_id' => $user->id, 'phone_number' => $phoneNumber] as $column => $value) {
                $recentCodes = WhatsAppVerificationCode::query()->where($column, $value)
                    ->where('created_at', '>', now()->subHour())->orderBy('created_at')->get();
                $latest = $recentCodes->last();

                if ($latest !== null && $latest->created_at->greaterThan(now()->subMinute())) {
                    $retryAfter = max(1, (int) ceil(now()->diffInSeconds($latest->created_at->addMinute(), false)));
                    throw new HttpException(429, 'Espera un minuto antes de solicitar otro código.', null, ['Retry-After' => (string) $retryAfter]);
                }

                if ($recentCodes->count() >= 5) {
                    $retryAfter = max(1, (int) ceil(now()->diffInSeconds($recentCodes->first()->created_at->addHour(), false)));
                    throw new HttpException(429, 'Alcanzaste el límite de cinco envíos por hora.', null, ['Retry-After' => (string) $retryAfter]);
                }
            }

            WhatsAppVerificationCode::query()->where('user_id', $user->id)
                ->whereIn('status', ['sending', 'sent'])->update(['status' => 'invalidated']);

            return WhatsAppVerificationCode::query()->create([
                'user_id' => $user->id,
                'phone_number' => $phoneNumber,
                'code_hash' => Hash::make($plainCode),
                'status' => 'sending',
                'expires_at' => now()->addHours(24),
            ]);
        }, 3);

        try {
            $this->sendCode->execute($phoneNumber, $plainCode);
        } catch (Throwable $exception) {
            WhatsAppVerificationCode::query()->whereKey($code->id)->where('status', 'sending')->update(['status' => 'failed']);
            throw $exception;
        }

        $updated = WhatsAppVerificationCode::query()->whereKey($code->id)->where('status', 'sending')->update(['status' => 'sent']);

        if ($updated === 0) {
            throw new HttpException(409, 'La solicitud fue cancelada. Solicita un nuevo código.');
        }
    }
}
