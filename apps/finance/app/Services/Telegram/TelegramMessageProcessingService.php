<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramServiceInterface;
use App\Services\Legal\GetLegalAcceptanceState;
use App\Services\Telegram\Helpers\TelegramUserHelper;

use function config;
use function data_get;

use Exception;
use Illuminate\Support\Facades\Log;

use function rtrim;

class TelegramMessageProcessingService
{
    public function __construct(
        private readonly TelegramMessageProcessorFactory $processorFactory,
        private readonly TelegramServiceInterface $telegramService,
        private readonly GetLegalAcceptanceState $getLegalAcceptanceState,
    ) {
    }

    public function processWebhookUpdate(array $telegramUpdate): void
    {
        $chatId = data_get($telegramUpdate, 'message.chat.id');

        if (empty($chatId)) {
            Log::warning('Webhook de Telegram recibido sin chat ID', $telegramUpdate);

            return;
        }

        $user = TelegramUserHelper::getAuthenticatedUser($telegramUpdate);

        if (null !== $user && $this->getLegalAcceptanceState->execute($user)['required']) {
            $url = rtrim((string) config('app.url'), '/') . '/auth/legal-acceptance';
            $this->telegramService->sendMessage(
                (string) $chatId,
                'Antes de usar el bot, revisa y acepta los términos y el aviso de privacidad en ' . $url,
            );

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
        } catch (Exception $e) {
            Log::error('Error procesando mensaje de Telegram', [
                'processor' => $processor::class,
                'error'     => $e->getMessage(),
                'update'    => $telegramUpdate,
            ]);

            $this->telegramService->sendMessage(
                (string) $chatId,
                'Ocurrió un error al procesar tu mensaje. Por favor intenta nuevamente.'
            );
        }
    }
}
