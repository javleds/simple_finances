<?php

namespace App\Services\Telegram;

use App\Contracts\MessageActionDetectionServiceInterface;
use App\Dto\MessageActionDetectionDto;
use App\Enums\MessageAction;
use Illuminate\Support\Facades\Log;

class DummyMessageActionDetectionService implements MessageActionDetectionServiceInterface
{
    public function detectAction(string $text): array
    {
        Log::info('DummyMessageActionDetectionService: Processing text (dummy mode)', ['text' => $text]);

        $text = strtolower($text);
        $action = MessageAction::CreateTransaction; // Default
        $context = [];

        // Lógica simple basada en palabras clave
        if (str_contains($text, 'balance') || str_contains($text, 'saldo') || str_contains($text, 'cuánto')) {
            $action = MessageAction::QueryBalance;
            $context['detected_intent'] = 'balance_query';
        } elseif (str_contains($text, 'movimientos') || str_contains($text, 'historial') || str_contains($text, 'últimas')) {
            $action = MessageAction::QueryRecentTransactions;
            $context['detected_intent'] = 'history_query';
        } elseif (str_contains($text, 'modificar') || str_contains($text, 'cambiar') || str_contains($text, 'corregir')) {
            $action = MessageAction::ModifyLastTransaction;
            $context['detected_intent'] = 'modify_transaction';
        } elseif (str_contains($text, 'eliminar') || str_contains($text, 'borrar') || str_contains($text, 'quitar')) {
            $action = MessageAction::DeleteLastTransaction;
            $context['detected_intent'] = 'delete_transaction';
        }

        $dto = new MessageActionDetectionDto(
            success: true,
            action: $action,
            context: $context,
            error: null,
            rawResponse: ['dummy' => true, 'service' => 'DummyMessageActionDetectionService']
        );

        return $dto->toArray();
    }

    public function isAvailable(): bool
    {
        return true; // Siempre disponible para testing
    }
}
