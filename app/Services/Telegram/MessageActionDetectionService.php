<?php

namespace App\Services\Telegram;

use App\Contracts\MessageActionDetectionServiceInterface;
use App\Contracts\OpenAIServiceInterface;
use App\Dto\MessageActionDetectionDto;
use App\Enums\MessageAction;
use Illuminate\Support\Facades\Log;

class MessageActionDetectionService implements MessageActionDetectionServiceInterface
{
    public function __construct(
        private readonly OpenAIServiceInterface $openAIService
    ) {}

    public function detectAction(string $text): array
    {
        try {
            if (!$this->isAvailable()) {
                Log::warning('MessageActionDetectionService: Service not available');
                return $this->buildFallbackResponse($text);
            }

            $response = $this->openAIService->detectMessageAction($text);

            if (!$response['success']) {
                Log::error('MessageActionDetectionService: Detection failed', ['error' => $response['error']]);
                return $this->buildFallbackResponse($text);
            }

            // Asegurar que el texto original esté en el contexto
            if (!isset($response['context'])) {
                $response['context'] = [];
            }
            $response['context']['original_text'] = $text;

            return $response;
        } catch (\Exception $e) {
            Log::error('MessageActionDetectionService: Exception occurred', [
                'error' => $e->getMessage(),
                'text' => $text
            ]);
            return $this->buildFallbackResponse($text);
        }
    }

    public function isAvailable(): bool
    {
        // Verificar si OpenAI está disponible y configurado
        $apiToken = config('services.openai.api_token');
        return !empty($apiToken);
    }

    private function buildFallbackResponse(string $text): array
    {
        // Fallback simple: siempre asumir que es crear transacción
        // Esto mantiene compatibilidad con el flujo existente
        $dto = new MessageActionDetectionDto(
            success: true,
            action: MessageAction::CreateTransaction,
            context: ['fallback' => true, 'original_text' => $text],
            error: null,
            rawResponse: ['fallback' => true]
        );

        return $dto->toArray();
    }
}
