<?php

namespace App\Services\Telegram;

use App\Contracts\TelegramServiceInterface;
use Illuminate\Support\Facades\Log;

class TelegramMessageProcessingService
{
    public function __construct(
        private readonly TelegramMessageProcessorFactory $processorFactory,
        private readonly TelegramServiceInterface $telegramService
    ) {}

    public function processWebhookUpdate(array $telegramUpdate): void
    {
        $chatId = data_get($telegramUpdate, 'message.chat.id');

        if (empty($chatId)) {
            Log::warning('Webhook de Telegram recibido sin chat ID', $telegramUpdate);

            return;
        }

        $processor = $this->processorFactory->getProcessor($telegramUpdate);

        if (! $processor) {
            Log::warning('No se encontró procesador para el mensaje de Telegram', [
                'update' => $telegramUpdate,
            ]);

            $this->telegramService->sendMessage(
                (string) $chatId,
                'Lo siento, no puedo procesar este tipo de mensaje en este momento.'
            );

            return;
        }

        try {
            $response = $processor->process($telegramUpdate);

            if (empty($response)) {
                $this->telegramService->sendMessage((string) $chatId, 'Mensaje procesado correctamente 👌🏼.');

                return;
            }

            $this->telegramService->sendMessage((string) $chatId, $response);
        } catch (\Exception $e) {
            Log::error('Error procesando mensaje de Telegram', [
                'processor' => get_class($processor),
                'error' => $e->getMessage(),
                'update' => $telegramUpdate,
            ]);

            $this->telegramService->sendMessage(
                (string) $chatId,
                'Ocurrió un error al procesar tu mensaje. Por favor intenta nuevamente.'
            );
        }
    }
}
