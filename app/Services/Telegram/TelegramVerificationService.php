<?php

namespace App\Services\Telegram;

use App\Models\TelegramVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TelegramVerificationService
{
    public function __construct(
        private readonly TelegramService $telegramService
    ) {}

    /**
     * Genera un código de verificación para un usuario
     */
    public function generateCode(User $user, int $expirationMinutes = 10): TelegramVerificationCode
    {
        // Invalidar códigos anteriores del usuario
        $this->invalidateExistingCodes($user);

        // Crear nuevo código
        $verificationCode = TelegramVerificationCode::createForUser($user, $expirationMinutes);

        Log::info('Código de verificación generado para usuario', [
            'user_id' => $user->id,
            'code' => $verificationCode->code,
            'expires_at' => $verificationCode->expires_at,
        ]);

        return $verificationCode;
    }

    /**
     * Verifica un código de verificación
     */
    public function verifyCode(string $code, string $chatId): ?User
    {
        $verificationCode = TelegramVerificationCode::where('code', $code)
            ->valid()
            ->with('user')
            ->first();

        if (! $verificationCode) {
            Log::warning('Intento de verificación con código inválido', [
                'code' => $code,
                'chat_id' => $chatId,
            ]);

            return null;
        }

        // Verificar que el usuario no tenga ya una cuenta vinculada
        if ($verificationCode->user->hasTelegramLinked()) {
            Log::warning('Usuario ya tiene Telegram vinculado', [
                'user_id' => $verificationCode->user->id,
                'existing_chat_id' => $verificationCode->user->telegram_chat_id,
                'new_chat_id' => $chatId,
            ]);

            return null;
        }

        // Marcar código como usado
        $verificationCode->markAsUsed();

        // Vincular usuario con Telegram
        $this->linkUserToTelegram($verificationCode->user, $chatId);

        Log::info('Usuario vinculado exitosamente con Telegram', [
            'user_id' => $verificationCode->user->id,
            'chat_id' => $chatId,
            'code_used' => $code,
        ]);

        return $verificationCode->user;
    }

    /**
     * Vincula un usuario con su chat de Telegram
     */
    private function linkUserToTelegram(User $user, string $chatId): void
    {
        $user->update(['telegram_chat_id' => $chatId]);
    }

    /**
     * Invalida códigos existentes de un usuario
     */
    private function invalidateExistingCodes(User $user): void
    {
        TelegramVerificationCode::where('user_id', $user->id)
            ->valid()
            ->update(['used_at' => now()]);
    }

    /**
     * Limpia códigos expirados
     */
    public function cleanExpiredCodes(): int
    {
        $deletedCount = TelegramVerificationCode::expired()->delete();

        Log::info('Códigos de verificación expirados eliminados', [
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }

    /**
     * Envía mensaje de confirmación al usuario en Telegram
     */
    public function sendConfirmationMessage(User $user): void
    {
        if (! $user->hasTelegramLinked()) {
            return;
        }

        try {
            $message = "¡Excelente {$user->name}! Tu cuenta ha sido vinculada exitosamente con Telegram. ".
                      'Ahora recibirás notificaciones de tus finanzas directamente aquí.';

            $this->telegramService->sendMessage($user->telegram_chat_id, $message);

            Log::info('Mensaje de confirmación enviado', [
                'user_id' => $user->id,
                'chat_id' => $user->telegram_chat_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando mensaje de confirmación', [
                'user_id' => $user->id,
                'chat_id' => $user->telegram_chat_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Desvincula un usuario de Telegram
     */
    public function unlinkUser(User $user): void
    {
        $oldChatId = $user->telegram_chat_id;

        $user->update(['telegram_chat_id' => null]);

        // Invalidar códigos pendientes
        $this->invalidateExistingCodes($user);

        Log::info('Usuario desvinculado de Telegram', [
            'user_id' => $user->id,
            'old_chat_id' => $oldChatId,
        ]);
    }

    /**
     * Verifica si un usuario puede generar un nuevo código
     */
    public function canGenerateNewCode(User $user): bool
    {
        // Verificar que no tenga ya Telegram vinculado
        if ($user->hasTelegramLinked()) {
            return false;
        }

        // Verificar rate limiting (máximo 3 códigos en 1 hora)
        $recentCodes = TelegramVerificationCode::where('user_id', $user->id)
            ->where('created_at', '>', now()->subHour())
            ->count();

        return $recentCodes < 3;
    }
}
