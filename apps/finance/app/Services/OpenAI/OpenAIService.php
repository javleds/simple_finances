<?php

declare(strict_types=1);

namespace App\Services\OpenAI;

use App\Contracts\OpenAIServiceInterface;
use App\Dto\MessageActionDetectionDto;
use App\Dto\OpenAIResponseDto;
use App\Dto\TransactionExtractionDto;
use App\Enums\MessageAction;
use App\Services\OpenAI\Prompts\MessageActionDetectionPrompt;
use App\Services\OpenAI\Prompts\TransactionExtractionPrompt;

use function array_key_exists;
use function base64_encode;
use function config;

use Exception;

use function file_exists;
use function file_get_contents;
use function fopen;

use Illuminate\Support\Facades\Log;

use function json_decode;
use function mime_content_type;

use OpenAI;

use function sleep;

use Throwable;

class OpenAIService implements OpenAIServiceInterface
{
    public function __construct(private readonly NormalizeExtractedAmount $normalizeExtractedAmount)
    {
        $this->config = config('services.openai');
        $this->client = OpenAI::client($this->config['api_token']);
    }

    public function detectMessageAction(string $text): array
    {
        try {
            Log::info('OpenAI: Detecting message action', ['text' => $text]);

            $response = $this->client->chat()->create([
                'model'           => $this->config['default_model'],
                'messages'        => [
                    [
                        'role'    => 'system',
                        'content' => MessageActionDetectionPrompt::getSystemPrompt(),
                    ],
                    [
                        'role'    => 'user',
                        'content' => MessageActionDetectionPrompt::getUserPrompt($text),
                    ],
                ],
                'max_tokens'      => 500,
                'temperature'     => $this->config['temperature'],
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $data = json_decode($content, true);

            Log::info('OpenAI: Action detection successful', ['response' => $data]);

            return $this->buildActionDetectionResponse($data, $response->toArray());
        } catch (Throwable $e) {
            Log::error('OpenAI: Action detection failed', [
                'error' => $e->getMessage(),
                'text'  => $text,
            ]);

            return $this->buildActionDetectionErrorResponse($e->getMessage());
        }
    }

    public function processText(string $text): array
    {
        try {
            Log::info('OpenAI: Processing text', ['text' => $text]);

            $response = $this->client->chat()->create([
                'model'           => $this->config['default_model'],
                'messages'        => [
                    [
                        'role'    => 'system',
                        'content' => TransactionExtractionPrompt::getSystemPrompt(),
                    ],
                    [
                        'role'    => 'user',
                        'content' => TransactionExtractionPrompt::getUserPrompt($text),
                    ],
                ],
                'max_tokens'      => $this->config['max_tokens'],
                'temperature'     => $this->config['temperature'],
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $data = json_decode($content, true);

            Log::info('OpenAI: Text processing successful', ['response' => $data]);

            return $this->buildSuccessResponse($data, $response->toArray());
        } catch (Throwable $e) {
            Log::error('OpenAI: Text processing failed', [
                'error' => $e->getMessage(),
                'text'  => $text,
            ]);

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    public function processImage(string $imagePath, string $caption = ''): array
    {
        try {
            Log::info('OpenAI: Processing image', ['imagePath' => $imagePath]);

            if (! file_exists($imagePath)) {
                throw new Exception("Image file not found: {$imagePath}");
            }

            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            $userMessage = 'Analiza esta imagen y extrae la información de transacción financiera que puedas encontrar.';
            if (! empty($caption)) {
                $userMessage .= ' Además, ten en cuenta el siguiente texto que la acompaña: ' . $caption;
            }

            $response = $this->client->chat()->create([
                'model'           => $this->config['vision_model'],
                'messages'        => [
                    [
                        'role'    => 'system',
                        'content' => TransactionExtractionPrompt::getSystemPrompt(),
                    ],
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $userMessage,
                            ],
                            [
                                'type'      => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}",
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens'      => $this->config['max_tokens'],
                'temperature'     => $this->config['temperature'],
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $data = json_decode($content, true);

            Log::info('OpenAI: Image processing successful', ['response' => $data]);

            return $this->buildSuccessResponse($data, $response->toArray());
        } catch (Throwable $e) {
            Log::error('OpenAI: Image processing failed', [
                'error'     => $e->getMessage(),
                'imagePath' => $imagePath,
            ]);

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    public function transcribeAudio(string $audioPath): array
    {
        try {
            Log::info('OpenAI: Transcribing audio', ['audioPath' => $audioPath]);

            if (! file_exists($audioPath)) {
                throw new Exception("Audio file not found: {$audioPath}");
            }

            // Transcribir el audio
            $transcriptionResponse = $this->client->audio()->transcribe([
                'model'           => $this->config['audio_model'],
                'file'            => fopen($audioPath, 'r'),
                'language'        => 'es',
                'response_format' => 'text',
            ]);

            $transcribedText = $transcriptionResponse->text;

            Log::info('OpenAI: Audio transcription successful', ['transcription' => $transcribedText]);

            return [
                'success' => true,
                'text'    => $transcribedText,
                'error'   => null,
            ];
        } catch (Throwable $e) {
            Log::error('OpenAI: Audio transcription failed', [
                'error'     => $e->getMessage(),
                'audioPath' => $audioPath,
            ]);

            return [
                'success' => false,
                'text'    => null,
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function processAudio(string $audioPath): array
    {
        try {
            Log::info('OpenAI: Processing audio', ['audioPath' => $audioPath]);

            if (! file_exists($audioPath)) {
                throw new Exception("Audio file not found: {$audioPath}");
            }

            // Primero transcribir el audio
            $transcriptionResponse = $this->client->audio()->transcribe([
                'model'           => $this->config['audio_model'],
                'file'            => fopen($audioPath, 'r'),
                'language'        => 'es',
                'response_format' => 'text',
            ]);

            $transcribedText = $transcriptionResponse->text;

            Log::info('OpenAI: Audio transcription successful', ['transcription' => $transcribedText]);

            // Luego procesar el texto transcrito
            return $this->processText($transcribedText);
        } catch (Throwable $e) {
            Log::error('OpenAI: Audio processing failed', [
                'error'     => $e->getMessage(),
                'audioPath' => $audioPath,
            ]);

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    private OpenAI\Client $client;

    private array $config;

    private function buildSuccessResponse(array $data, array $rawResponse): array
    {
        $dto = new TransactionExtractionDto(
            account: $data['account'] ?? null,
            amount: $this->normalizeExtractedAmount->execute($data['amount'] ?? null),
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

    private function buildActionDetectionResponse(array $data, array $rawResponse): array
    {
        $action = null;
        if (isset($data['action']) && ! empty($data['action'])) {
            $action = MessageAction::tryFrom($data['action']);
        }

        $context = $data['context'] ?? [];
        if (array_key_exists('amount', $context)) {
            $context['amount'] = $this->normalizeExtractedAmount->execute($context['amount']);
        }

        $dto = new MessageActionDetectionDto(
            success: true,
            action: $action,
            context: $context,
            error: null,
            rawResponse: $rawResponse
        );

        return $dto->toArray();
    }

    private function buildActionDetectionErrorResponse(string $error): array
    {
        $dto = new MessageActionDetectionDto(
            success: false,
            action: null,
            context: [],
            error: $error
        );

        return $dto->toArray();
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
                ++$attempt;

                if ($attempt >= $maxRetries) {
                    throw $e;
                }

                Log::warning("OpenAI: Retry attempt {$attempt}/{$maxRetries}", [
                    'error' => $e->getMessage(),
                ]);

                sleep(2 ** $attempt); // Exponential backoff
            }
        }

        throw $lastException ?? new Exception('Retry operation failed');
    }
}
