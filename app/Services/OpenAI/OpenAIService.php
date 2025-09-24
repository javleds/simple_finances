<?php

namespace App\Services\OpenAI;

use App\Contracts\OpenAIServiceInterface;
use App\Dto\OpenAIRequestDto;
use App\Dto\OpenAIResponseDto;
use App\Dto\TransactionExtractionDto;
use App\Services\OpenAI\Prompts\TransactionExtractionPrompt;
use Illuminate\Support\Facades\Log;
use OpenAI;
use Throwable;

class OpenAIService implements OpenAIServiceInterface
{
    private OpenAI\Client $client;
    private array $config;

    public function __construct()
    {
        $this->config = config('services.openai');
        $this->client = OpenAI::client($this->config['api_token']);
    }

    public function processText(string $text): array
    {
        try {
            Log::info('OpenAI: Processing text', ['text' => $text]);

            $response = $this->client->chat()->create([
                'model' => $this->config['default_model'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => TransactionExtractionPrompt::getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => TransactionExtractionPrompt::getUserPrompt($text)
                    ]
                ],
                'max_tokens' => $this->config['max_tokens'],
                'temperature' => $this->config['temperature'],
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $data = json_decode($content, true);

            Log::info('OpenAI: Text processing successful', ['response' => $data]);

            return $this->buildSuccessResponse($data, $response->toArray());

        } catch (Throwable $e) {
            Log::error('OpenAI: Text processing failed', [
                'error' => $e->getMessage(),
                'text' => $text
            ]);

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    public function processImage(string $imagePath): array
    {
        try {
            Log::info('OpenAI: Processing image', ['imagePath' => $imagePath]);

            if (!file_exists($imagePath)) {
                throw new \Exception("Image file not found: {$imagePath}");
            }

            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            $response = $this->client->chat()->create([
                'model' => $this->config['vision_model'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => TransactionExtractionPrompt::getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analiza esta imagen y extrae la información de transacción financiera que puedas encontrar.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => $this->config['max_tokens'],
                'temperature' => $this->config['temperature'],
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $data = json_decode($content, true);

            Log::info('OpenAI: Image processing successful', ['response' => $data]);

            return $this->buildSuccessResponse($data, $response->toArray());

        } catch (Throwable $e) {
            Log::error('OpenAI: Image processing failed', [
                'error' => $e->getMessage(),
                'imagePath' => $imagePath
            ]);

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    public function processAudio(string $audioPath): array
    {
        try {
            Log::info('OpenAI: Processing audio', ['audioPath' => $audioPath]);

            if (!file_exists($audioPath)) {
                throw new \Exception("Audio file not found: {$audioPath}");
            }

            // Primero transcribir el audio
            $transcriptionResponse = $this->client->audio()->transcribe([
                'model' => $this->config['audio_model'],
                'file' => fopen($audioPath, 'r'),
                'language' => 'es',
                'response_format' => 'text',
            ]);

            $transcribedText = $transcriptionResponse->text;

            Log::info('OpenAI: Audio transcription successful', ['transcription' => $transcribedText]);

            // Luego procesar el texto transcrito
            return $this->processText($transcribedText);

        } catch (Throwable $e) {
            Log::error('OpenAI: Audio processing failed', [
                'error' => $e->getMessage(),
                'audioPath' => $audioPath
            ]);

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    private function buildSuccessResponse(array $data, array $rawResponse): array
    {
        $dto = new TransactionExtractionDto(
            account: $data['account'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            type: $data['type'] ?? null,
            concept: $data['concept'] ?? null,
            date: $data['date'] ?? null,
            financialGoal: $data['financial_goal'] ?? null,
        );

        $response = new OpenAIResponseDto(
            success: true,
            data: $dto,
            rawResponse: $rawResponse
        );

        return $response->toArray();
    }

    private function buildErrorResponse(string $error): array
    {
        $response = new OpenAIResponseDto(
            success: false,
            error: $error
        );

        return $response->toArray();
    }

    private function retryOperation(callable $operation, int $maxRetries = 3): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $operation();
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $maxRetries) {
                    throw $e;
                }

                Log::warning("OpenAI: Retry attempt {$attempt}/{$maxRetries}", [
                    'error' => $e->getMessage()
                ]);

                sleep(pow(2, $attempt)); // Exponential backoff
            }
        }

        throw $lastException ?? new \Exception('Retry operation failed');
    }
}
