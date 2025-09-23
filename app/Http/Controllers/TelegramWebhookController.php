<?php

namespace App\Http\Controllers;

use App\Contracts\TelegramServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(private readonly TelegramServiceInterface $telegramService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            logger()->debug('Webhook de Telegram recibido', $request->all());

            $chatId = data_get($request->all(), 'message.chat.id');

            $messageText = data_get($request->all(), 'message.text');

            if (empty($chatId)) {
                Log::warning('Webhook de Telegram recibido sin chat ID', $request->all());
                return response()->json(['message' => 'No chat ID!'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($messageText)) {
                $messageText = 'Mensaje recibido';
            }

            $responseMessage = "Has enviado: {$messageText}";

            $this->telegramService->sendMessage((string) $chatId, $responseMessage);

            return response()->json([], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error procesando webhook de Telegram', [
                'error' => $e->getMessage(),
                'webhook_data' => $request->all()
            ]);

            return response()->json(['message' => 'Error procesando webhook de Telegram'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
