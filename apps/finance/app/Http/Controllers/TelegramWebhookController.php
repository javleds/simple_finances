<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramMessageProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramMessageProcessingService $messageProcessingService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            logger()->debug('Webhook de Telegram recibido', $request->all());

            $this->messageProcessingService->processWebhookUpdate($request->all());

            return response()->json([], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error procesando webhook de Telegram', [
                'error' => $e->getMessage(),
                'webhook_data' => $request->all(),
            ]);

            return response()->json(['message' => 'Error procesando webhook de Telegram'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
