<?php

namespace App\Services\Telegram\Processors;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\Helpers\TelegramMessageHelper;
use App\Services\Telegram\Helpers\TelegramUserHelper;
use App\Services\Transaction\TransactionProcessorService;

class TextMessageProcessor implements TelegramMessageProcessorInterface
{
    public function __construct(
        private readonly TransactionProcessorService $transactionProcessor
    ) {}

    public static function getMessageType(): string
    {
        return 'text';
    }

    public function canHandle(array $telegramUpdate): bool
    {
        return TelegramMessageHelper::hasText($telegramUpdate)
            && !$this->isCommand($telegramUpdate);
    }

    public function process(array $telegramUpdate): string
    {
        $messageText = TelegramMessageHelper::getText($telegramUpdate);
        $userName = TelegramMessageHelper::getUserName($telegramUpdate);
        
        // Verificar si el usuario está autenticado
        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);
        
        if (!$user) {
            return "Hola {$userName}! Para poder crear transacciones, primero necesitas verificar tu cuenta. Usa el comando /start para comenzar el proceso de verificación.";
        }

        // Si el mensaje parece una transacción, procesarlo con IA
        if ($this->seemsLikeTransaction($messageText)) {
            return $this->transactionProcessor->processText($messageText, $user);
        }

        // Respuesta por defecto para otros mensajes de texto
        return "¡Hola {$userName}! Puedo ayudarte a crear transacciones. Describe tu transacción incluyendo la cuenta, monto y tipo (ingreso o gasto). Por ejemplo: 'Deposité 1500 pesos en mi cuenta de ahorros' o 'Gasté 250 en supermercado con mi tarjeta'.";
    }

    public function getPriority(): int
    {
        return 10;
    }

    private function isCommand(array $telegramUpdate): bool
    {
        $text = TelegramMessageHelper::getText($telegramUpdate);
        return str_starts_with(trim($text), '/');
    }

    private function seemsLikeTransaction(string $text): bool
    {
        $text = mb_strtolower($text);
        
        // Palabras clave que sugieren una transacción
        $transactionKeywords = [
            'gast', 'deposit', 'ingres', 'cobr', 'pag', 'retir', 
            'compré', 'vendí', 'recibí', 'transferí', 'ahorre',
            'cuenta', 'tarjeta', 'efectivo', 'pesos', '$', 'dinero',
            'banco', 'oxxo', 'supermercado', 'gasolina', 'comida'
        ];

        foreach ($transactionKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        // También verificar si contiene números (posibles montos)
        return preg_match('/\d+/', $text);
    }
}
